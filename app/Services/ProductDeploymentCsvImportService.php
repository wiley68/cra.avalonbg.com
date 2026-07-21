<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Models\User;
use App\Support\CsvImportValueParser;
use App\Support\CsvReader;
use App\Support\Translations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductDeploymentCsvImportService
{
    public function __construct(
        private readonly ProductDeploymentService $deployments,
    ) {
    }

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function import(Product $product, UploadedFile $file, User $actor): array
    {
        $parsed = CsvReader::read($file);

        if (
            !in_array('environment', $parsed['headers'], true)
            || (
                !in_array('customer_name', $parsed['headers'], true)
                && !in_array('customer_external_ref', $parsed['headers'], true)
            )
        ) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('products.deployments.import.missing_columns')],
            ]);
        }

        $customers = Customer::query()
            ->where('organization_id', $product->organization_id)
            ->get(['id', 'name', 'external_ref']);

        $customersByName = $customers->keyBy(fn(Customer $customer) => mb_strtolower($customer->name));
        $customersByRef = $customers
            ->filter(fn(Customer $customer) => filled($customer->external_ref))
            ->keyBy(fn(Customer $customer) => mb_strtolower((string) $customer->external_ref));

        $versionsByNumber = ProductVersion::query()
            ->where('product_id', $product->id)
            ->get(['id', 'version_number'])
            ->keyBy(fn(ProductVersion $version) => mb_strtolower($version->version_number));

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($product, $parsed, $actor, $customersByName, $customersByRef, $versionsByNumber, &$result, ): void {
            foreach ($parsed['rows'] as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    $outcome = $this->importRow(
                        $product,
                        $row,
                        $actor,
                        $customersByName,
                        $customersByRef,
                        $versionsByNumber,
                    );
                    $result[$outcome]++;
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->first();

                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'message' => is_string($message) ? $message : Translations::get('common.import.row_failed'),
                    ];
                }
            }
        });

        if (
            $result['created'] === 0
            && $result['updated'] === 0
            && $result['skipped'] === 0
            && $result['errors'] !== []
        ) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('products.deployments.import.all_rows_failed')],
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  \Illuminate\Support\Collection<string, Customer>  $customersByName
     * @param  \Illuminate\Support\Collection<string, Customer>  $customersByRef
     * @param  \Illuminate\Support\Collection<string, ProductVersion>  $versionsByNumber
     * @return 'created'|'updated'|'skipped'
     */
    private function importRow(
        Product $product,
        array $row,
        User $actor,
        $customersByName,
        $customersByRef,
        $versionsByNumber,
    ): string {
        $customer = $this->resolveCustomer($row, $customersByName, $customersByRef);
        $environment = CsvImportValueParser::environment($row['environment'] ?? null);
        $versionNumber = $row['version_number'] ?? null;
        $productVersionId = null;

        if ($versionNumber !== null) {
            $version = $versionsByNumber->get(mb_strtolower($versionNumber));

            if ($version === null) {
                throw ValidationException::withMessages([
                    'version_number' => [Translations::get('products.deployments.import.invalid_version')],
                ]);
            }

            $productVersionId = $version->id;
        }

        $attributes = [
            'customer_id' => $customer->id,
            'product_version_id' => $productVersionId,
            'environment' => $environment,
            'installation_date' => CsvImportValueParser::date($row['installation_date'] ?? null),
            'internet_exposure' => CsvImportValueParser::bool($row['internet_exposure'] ?? null),
            'update_channel' => $row['update_channel'] ?? null,
            'last_confirmed_at' => CsvImportValueParser::date($row['last_confirmed_at'] ?? null),
            'custom_modifications' => CsvImportValueParser::bool($row['custom_modifications'] ?? null),
            'end_of_support_exception' => CsvImportValueParser::bool($row['end_of_support_exception'] ?? null),
            'notes' => $row['notes'] ?? null,
        ];

        $existing = ProductDeployment::query()
            ->where('product_id', $product->id)
            ->where('customer_id', $customer->id)
            ->where('environment', $environment->value)
            ->first();

        if ($existing === null) {
            $this->deployments->create($product, $attributes, $actor);

            return 'created';
        }

        if ($this->attributesMatch($existing, $attributes)) {
            return 'skipped';
        }

        $this->deployments->update($existing, $attributes, $actor);

        return 'updated';
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  \Illuminate\Support\Collection<string, Customer>  $customersByName
     * @param  \Illuminate\Support\Collection<string, Customer>  $customersByRef
     */
    private function resolveCustomer(array $row, $customersByName, $customersByRef): Customer
    {
        $externalRef = $row['customer_external_ref'] ?? null;

        if ($externalRef !== null) {
            $customer = $customersByRef->get(mb_strtolower($externalRef));

            if ($customer !== null) {
                return $customer;
            }
        }

        $customerName = $row['customer_name'] ?? null;

        if ($customerName !== null) {
            $customer = $customersByName->get(mb_strtolower($customerName));

            if ($customer !== null) {
                return $customer;
            }
        }

        throw ValidationException::withMessages([
            'customer' => [Translations::get('products.deployments.import.customer_not_found')],
        ]);
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     product_version_id?: int|null,
     *     environment: \App\Enums\DeploymentEnvironment,
     *     installation_date?: string|null,
     *     internet_exposure?: bool,
     *     update_channel?: string|null,
     *     last_confirmed_at?: string|null,
     *     custom_modifications?: bool,
     *     end_of_support_exception?: bool,
     *     notes?: string|null
     * }  $attributes
     */
    private function attributesMatch(ProductDeployment $deployment, array $attributes): bool
    {
        return $deployment->product_version_id === ($attributes['product_version_id'] ?? null)
            && $deployment->installation_date?->toDateString() === ($attributes['installation_date'] ?? null)
            && $deployment->internet_exposure === ($attributes['internet_exposure'] ?? false)
            && $deployment->update_channel === ($attributes['update_channel'] ?? null)
            && $deployment->last_confirmed_at?->toDateString() === ($attributes['last_confirmed_at'] ?? null)
            && $deployment->custom_modifications === ($attributes['custom_modifications'] ?? false)
            && $deployment->end_of_support_exception === ($attributes['end_of_support_exception'] ?? false)
            && $deployment->notes === ($attributes['notes'] ?? null);
    }
}

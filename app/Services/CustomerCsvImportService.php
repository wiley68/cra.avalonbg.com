<?php

namespace App\Services;

use App\Enums\CustomerCriticality;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Support\CsvImportValueParser;
use App\Support\CsvReader;
use App\Support\Translations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCsvImportService
{
    public function __construct(
        private readonly CustomerService $customers,
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
    public function import(Organization $organization, UploadedFile $file, User $actor): array
    {
        $parsed = CsvReader::read($file);

        if (!in_array('name', $parsed['headers'], true)) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('customers.import.missing_name_column')],
            ]);
        }

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($organization, $parsed, $actor, &$result): void {
            foreach ($parsed['rows'] as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    $outcome = $this->importRow($organization, $row, $actor);
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
                'file' => [Translations::get('customers.import.all_rows_failed')],
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return 'created'|'updated'|'skipped'
     */
    private function importRow(Organization $organization, array $row, User $actor): string
    {
        $name = $row['name'] ?? null;

        if ($name === null) {
            throw ValidationException::withMessages([
                'name' => [Translations::get('customers.import.missing_name')],
            ]);
        }

        $attributes = [
            'name' => $name,
            'external_ref' => $row['external_ref'] ?? null,
            'primary_contact' => $row['primary_contact'] ?? null,
            'criticality' => CsvImportValueParser::criticality($row['criticality'] ?? null),
            'notes' => $row['notes'] ?? null,
            'is_active' => CsvImportValueParser::bool($row['is_active'] ?? null, true),
        ];

        $existing = $this->findExistingCustomer($organization, $attributes['name'], $attributes['external_ref']);

        if ($existing === null) {
            $this->customers->create($organization, $attributes, $actor);

            return 'created';
        }

        if (
            $existing->name === $attributes['name']
            && ($attributes['external_ref'] === null || $existing->external_ref === $attributes['external_ref'])
            && $this->attributesMatch($existing, $attributes)
        ) {
            return 'skipped';
        }

        $this->customers->update($existing, $attributes, $actor);

        return 'updated';
    }

    /**
     * @param  array{
     *     name: string,
     *     external_ref?: string|null,
     *     primary_contact?: string|null,
     *     criticality: CustomerCriticality,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    private function attributesMatch(Customer $customer, array $attributes): bool
    {
        return $customer->external_ref === ($attributes['external_ref'] ?? null)
            && $customer->primary_contact === ($attributes['primary_contact'] ?? null)
            && $customer->criticality === $attributes['criticality']
            && $customer->notes === ($attributes['notes'] ?? null)
            && $customer->is_active === ($attributes['is_active'] ?? true);
    }

    private function findExistingCustomer(
        Organization $organization,
        string $name,
        ?string $externalRef,
    ): ?Customer {
        if ($externalRef !== null) {
            $byRef = Customer::query()
                ->where('organization_id', $organization->id)
                ->where('external_ref', $externalRef)
                ->first();

            if ($byRef !== null) {
                return $byRef;
            }
        }

        return Customer::query()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->first();
    }
}

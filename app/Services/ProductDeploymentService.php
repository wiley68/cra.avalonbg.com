<?php

namespace App\Services;

use App\Enums\DeploymentEnvironment;
use App\Enums\SupportStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProductDeploymentService
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     customer_id: int,
     *     customer_name: string,
     *     product_version_id: int|null,
     *     version_number: string|null,
     *     environment: string,
     *     installation_date: string|null,
     *     internet_exposure: bool,
     *     update_channel: string|null,
     *     last_confirmed_at: string|null,
     *     custom_modifications: bool,
     *     end_of_support_exception: bool
     * }>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'id',
        string $sortOrder = 'desc',
        string $search = '',
        bool $unsupportedOnly = false,
    ): LengthAwarePaginator {
        $query = ProductDeployment::query()
            ->where('product_deployments.product_id', $product->id)
            ->join('customers', 'customers.id', '=', 'product_deployments.customer_id')
            ->leftJoin('product_versions', 'product_versions.id', '=', 'product_deployments.product_version_id')
            ->select('product_deployments.*')
            ->with(['customer', 'productVersion']);

        if ($unsupportedOnly) {
            $this->applyUnsupportedVersionFilter($query);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.external_ref', 'like', "%{$search}%")
                    ->orWhere('product_deployments.environment', 'like', "%{$search}%")
                    ->orWhere('product_deployments.update_channel', 'like', "%{$search}%")
                    ->orWhere('product_versions.version_number', 'like', "%{$search}%")
                    ->orWhere('product_deployments.notes', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('product_deployments.id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'customer_name' => 'customers.name',
            'environment' => 'product_deployments.environment',
            'installation_date' => 'product_deployments.installation_date',
            'version_number' => 'product_versions.version_number',
            'internet_exposure' => 'product_deployments.internet_exposure',
            'support_status' => 'product_versions.support_status',
            'security_support_deadline' => 'product_versions.security_support_deadline',
            default => 'product_deployments.id',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(ProductDeployment $deployment) => $this->listItemPayload(
                $deployment,
                includeSupportFields: $unsupportedOnly,
            ));
    }

    public function countOnUnsupportedVersions(Product $product): int
    {
        return ProductDeployment::query()
            ->where('product_deployments.product_id', $product->id)
            ->join('product_versions', 'product_versions.id', '=', 'product_deployments.product_version_id')
            ->tap(fn(Builder $query) => $this->applyUnsupportedVersionFilter($query))
            ->count();
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     product_version_id?: int|null,
     *     environment: DeploymentEnvironment,
     *     installation_date?: string|null,
     *     internet_exposure?: bool,
     *     update_channel?: string|null,
     *     last_confirmed_at?: string|null,
     *     custom_modifications?: bool,
     *     end_of_support_exception?: bool,
     *     notes?: string|null
     * }  $attributes
     */
    public function create(Product $product, array $attributes, User $actor): ProductDeployment
    {
        $this->assertCustomerInOrganization($product, (int) $attributes['customer_id']);
        $this->assertUniqueEnvironment($product, (int) $attributes['customer_id'], $attributes['environment']);

        $deployment = ProductDeployment::query()->create([
            'organization_id' => $product->organization_id,
            'customer_id' => $attributes['customer_id'],
            'product_id' => $product->id,
            'product_version_id' => $attributes['product_version_id'] ?? null,
            'environment' => $attributes['environment'],
            'installation_date' => $attributes['installation_date'] ?? null,
            'internet_exposure' => $attributes['internet_exposure'] ?? false,
            'update_channel' => $attributes['update_channel'] ?? null,
            'last_confirmed_at' => $attributes['last_confirmed_at'] ?? null,
            'custom_modifications' => $attributes['custom_modifications'] ?? false,
            'end_of_support_exception' => $attributes['end_of_support_exception'] ?? false,
            'notes' => $attributes['notes'] ?? null,
        ]);

        AuditLogger::logDeploymentCreated($deployment->loadMissing(['customer', 'product']), $actor);

        return $deployment;
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     product_version_id?: int|null,
     *     environment: DeploymentEnvironment,
     *     installation_date?: string|null,
     *     internet_exposure?: bool,
     *     update_channel?: string|null,
     *     last_confirmed_at?: string|null,
     *     custom_modifications?: bool,
     *     end_of_support_exception?: bool,
     *     notes?: string|null
     * }  $attributes
     */
    public function update(ProductDeployment $deployment, array $attributes, User $actor): ProductDeployment
    {
        $product = $deployment->product()->firstOrFail();
        $this->assertCustomerInOrganization($product, (int) $attributes['customer_id']);
        $this->assertUniqueEnvironment(
            $product,
            (int) $attributes['customer_id'],
            $attributes['environment'],
            $deployment->id,
        );

        $deployment->update([
            'customer_id' => $attributes['customer_id'],
            'product_version_id' => $attributes['product_version_id'] ?? null,
            'environment' => $attributes['environment'],
            'installation_date' => $attributes['installation_date'] ?? null,
            'internet_exposure' => $attributes['internet_exposure'] ?? false,
            'update_channel' => $attributes['update_channel'] ?? null,
            'last_confirmed_at' => $attributes['last_confirmed_at'] ?? null,
            'custom_modifications' => $attributes['custom_modifications'] ?? false,
            'end_of_support_exception' => $attributes['end_of_support_exception'] ?? false,
            'notes' => $attributes['notes'] ?? null,
        ]);

        $fresh = $deployment->fresh(['customer', 'product']);
        AuditLogger::logDeploymentUpdated($fresh, $actor);

        return $fresh;
    }

    public function delete(ProductDeployment $deployment, User $actor): void
    {
        $deployment->loadMissing(['customer', 'product']);
        AuditLogger::logDeploymentDeleted($deployment, $actor);
        $deployment->delete();
    }

    private function assertCustomerInOrganization(Product $product, int $customerId): void
    {
        $exists = Customer::query()
            ->whereKey($customerId)
            ->where('organization_id', $product->organization_id)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'customer_id' => [Translations::get('products.deployments.customer_invalid')],
            ]);
        }
    }

    private function assertUniqueEnvironment(
        Product $product,
        int $customerId,
        DeploymentEnvironment $environment,
        ?int $ignoreId = null,
    ): void {
        $query = ProductDeployment::query()
            ->where('product_id', $product->id)
            ->where('customer_id', $customerId)
            ->where('environment', $environment->value);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'environment' => [Translations::get('products.deployments.environment_taken')],
            ]);
        }
    }

    /**
     * Deployments on versions marked unsupported or past security support deadline.
     * Excludes installations with a documented end-of-support exception.
     *
     * @param  Builder<ProductDeployment>  $query
     */
    private function applyUnsupportedVersionFilter(Builder $query): void
    {
        $today = Carbon::today()->toDateString();

        $query
            ->where('product_deployments.end_of_support_exception', false)
            ->whereNotNull('product_deployments.product_version_id')
            ->where(function (Builder $builder) use ($today): void {
                $builder
                    ->where('product_versions.support_status', SupportStatus::Unsupported->value)
                    ->orWhere(function (Builder $deadline) use ($today): void {
                        $deadline
                            ->whereNotNull('product_versions.security_support_deadline')
                            ->whereDate('product_versions.security_support_deadline', '<', $today);
                    });
            });
    }

    /**
     * @return array{
     *     id: int,
     *     customer_id: int,
     *     customer_name: string,
     *     product_version_id: int|null,
     *     version_number: string|null,
     *     environment: string,
     *     installation_date: string|null,
     *     internet_exposure: bool,
     *     update_channel: string|null,
     *     last_confirmed_at: string|null,
     *     custom_modifications: bool,
     *     end_of_support_exception: bool,
     *     support_status?: string|null,
     *     security_support_deadline?: string|null
     * }
     */
    private function listItemPayload(ProductDeployment $deployment, bool $includeSupportFields = false): array
    {
        $payload = [
            'id' => $deployment->id,
            'customer_id' => $deployment->customer_id,
            'customer_name' => $deployment->customer?->name ?? '',
            'product_version_id' => $deployment->product_version_id,
            'version_number' => $deployment->productVersion?->version_number,
            'environment' => $deployment->environment->value,
            'installation_date' => $deployment->installation_date?->toDateString(),
            'internet_exposure' => $deployment->internet_exposure,
            'update_channel' => $deployment->update_channel,
            'last_confirmed_at' => $deployment->last_confirmed_at?->toIso8601String(),
            'custom_modifications' => $deployment->custom_modifications,
            'end_of_support_exception' => $deployment->end_of_support_exception,
        ];

        if ($includeSupportFields) {
            $payload['support_status'] = $deployment->productVersion?->support_status?->value;
            $payload['security_support_deadline'] = $deployment->productVersion?->security_support_deadline?->toDateString();
        }

        return $payload;
    }
}

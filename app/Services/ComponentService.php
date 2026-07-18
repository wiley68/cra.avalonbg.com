<?php

namespace App\Services;

use App\Enums\ComponentSupportStatus;
use App\Enums\PackageEcosystem;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ComponentService
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     name: string,
     *     version: string|null,
     *     package_ecosystem: string,
     *     licence: string|null,
     *     is_direct: bool,
     *     is_dev: bool,
     *     support_status: string,
     *     product_version_id: int,
     *     version_number: string|null,
     *     purl: string|null
     * }>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'name',
        string $sortOrder = 'asc',
        string $search = '',
        ?int $versionId = null,
    ): LengthAwarePaginator {
        $query = ProductComponent::query()
            ->where('product_components.product_id', $product->id)
            ->leftJoin('product_versions', 'product_versions.id', '=', 'product_components.product_version_id')
            ->select('product_components.*')
            ->with('productVersion');

        if ($versionId !== null) {
            $query->where('product_components.product_version_id', $versionId);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('product_components.name', 'like', "%{$search}%")
                    ->orWhere('product_components.version', 'like', "%{$search}%")
                    ->orWhere('product_components.package_ecosystem', 'like', "%{$search}%")
                    ->orWhere('product_components.purl', 'like', "%{$search}%")
                    ->orWhere('product_components.licence', 'like', "%{$search}%")
                    ->orWhere('product_components.supplier', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('product_components.id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'product_components.id',
            'version' => 'product_components.version',
            'package_ecosystem' => 'product_components.package_ecosystem',
            'support_status' => 'product_components.support_status',
            'product_version_id' => 'product_components.product_version_id',
            default => 'product_components.name',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['product_components.*'], 'page', $page)
            ->through(fn(ProductComponent $component) => $this->listItemPayload($component));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Product $product, array $attributes): ProductComponent
    {
        $this->assertVersionBelongsToProduct($product, (int) $attributes['product_version_id']);

        return ProductComponent::query()->create([
            ...$attributes,
            'product_id' => $product->id,
            'sbom_id' => null,
        ])->load('productVersion');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(ProductComponent $component, array $attributes): ProductComponent
    {
        $this->assertVersionBelongsToProduct($component->product, (int) $attributes['product_version_id']);

        $component->update($attributes);

        return $component->fresh('productVersion');
    }

    public function delete(ProductComponent $component): void
    {
        $component->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertFromImport(Product $product, ProductVersion $version, ?int $sbomId, array $data): ProductComponent
    {
        $purl = isset($data['purl']) && is_string($data['purl']) && $data['purl'] !== ''
            ? $data['purl']
            : null;

        $query = ProductComponent::query()
            ->where('product_version_id', $version->id);

        if ($purl !== null) {
            $existing = (clone $query)->where('purl', $purl)->first();
        } else {
            $existing = (clone $query)
                ->whereNull('purl')
                ->where('package_ecosystem', $data['package_ecosystem'])
                ->where('name', $data['name'])
                ->where('version', $data['version'] ?? null)
                ->first();
        }

        $payload = [
            'product_id' => $product->id,
            'product_version_id' => $version->id,
            'sbom_id' => $sbomId,
            'name' => $data['name'],
            'supplier' => $data['supplier'] ?? null,
            'package_ecosystem' => $data['package_ecosystem'] instanceof PackageEcosystem
                ? $data['package_ecosystem']
                : PackageEcosystem::from((string) $data['package_ecosystem']),
            'version' => $data['version'] ?? null,
            'licence' => $data['licence'] ?? null,
            'purl' => $purl,
            'hash' => $data['hash'] ?? null,
            'is_direct' => (bool) ($data['is_direct'] ?? true),
            'is_dev' => (bool) ($data['is_dev'] ?? false),
            'usage_context' => $data['usage_context'] ?? null,
            'support_status' => $data['support_status'] instanceof ComponentSupportStatus
                ? $data['support_status']
                : ComponentSupportStatus::from((string) ($data['support_status'] ?? ComponentSupportStatus::Unknown->value)),
            'notes' => $data['notes'] ?? null,
        ];

        if ($existing !== null) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return ProductComponent::query()->create($payload);
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     version: string|null,
     *     package_ecosystem: string,
     *     licence: string|null,
     *     is_direct: bool,
     *     is_dev: bool,
     *     support_status: string,
     *     product_version_id: int,
     *     version_number: string|null,
     *     purl: string|null
     * }
     */
    public function listItemPayload(ProductComponent $component): array
    {
        return [
            'id' => $component->id,
            'name' => $component->name,
            'version' => $component->version,
            'package_ecosystem' => $component->package_ecosystem->value,
            'licence' => $component->licence,
            'is_direct' => $component->is_direct,
            'is_dev' => $component->is_dev,
            'support_status' => $component->support_status->value,
            'product_version_id' => $component->product_version_id,
            'version_number' => $component->productVersion?->version_number,
            'purl' => $component->purl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ProductComponent $component): array
    {
        return [
            'id' => $component->id,
            'product_version_id' => $component->product_version_id,
            'name' => $component->name,
            'supplier' => $component->supplier,
            'package_ecosystem' => $component->package_ecosystem->value,
            'version' => $component->version,
            'licence' => $component->licence,
            'purl' => $component->purl,
            'hash' => $component->hash,
            'is_direct' => $component->is_direct,
            'is_dev' => $component->is_dev,
            'usage_context' => $component->usage_context,
            'support_status' => $component->support_status->value,
            'notes' => $component->notes,
            'sbom_id' => $component->sbom_id,
            'version_number' => $component->productVersion?->version_number,
        ];
    }

    private function assertVersionBelongsToProduct(Product $product, int $versionId): void
    {
        $exists = ProductVersion::query()
            ->where('id', $versionId)
            ->where('product_id', $product->id)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'product_version_id' => 'The selected version does not belong to this product.',
            ]);
        }
    }
}

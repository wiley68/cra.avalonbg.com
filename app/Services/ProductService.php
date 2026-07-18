<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(
        private readonly ProductReadinessService $readiness,
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     product_type: string,
     *     classification_status: string,
     *     scope_status: string,
     *     product_line: string|null,
     *     module_statuses: array<string, 'empty'|'complete'|'incomplete'>
     * }>
     */
    public function paginate(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'name',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = Product::query()
            ->where('organization_id', $organization->id);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('product_line', 'like', "%{$search}%")
                    ->orWhere('product_type', 'like', "%{$search}%")
                    ->orWhere('classification_status', 'like', "%{$search}%")
                    ->orWhere('scope_status', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'slug' => 'slug',
            'product_type' => 'product_type',
            'classification_status' => 'classification_status',
            'scope_status' => 'scope_status',
            'product_line' => 'product_line',
            default => 'name',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'product_type' => $product->product_type->value,
                'classification_status' => $product->classification_status->value,
                'scope_status' => $product->scope_status->value,
                'product_line' => $product->product_line,
                'module_statuses' => $this->readiness->cardModuleStatuses($product),
            ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     version_number: string,
     *     state: string,
     *     support_status: string,
     *     release_date: string|null,
     *     security_support_deadline: string|null
     * }>
     */
    public function paginateVersions(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'version_number',
        string $sortOrder = 'desc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = $product->versions();

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('version_number', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('support_status', 'like', "%{$search}%")
                    ->orWhere('git_ref', 'like', "%{$search}%")
                    ->orWhere('build_identifier', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'state' => 'state',
            'support_status' => 'support_status',
            'release_date' => 'release_date',
            'security_support_deadline' => 'security_support_deadline',
            default => 'version_number',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'state' => $version->state->value,
                'support_status' => $version->support_status->value,
                'release_date' => $version->release_date?->toDateString(),
                'security_support_deadline' => $version->security_support_deadline?->toDateString(),
            ]);
    }
}

<?php

namespace App\Services;

use App\Enums\AuditorReviewPackageStatus;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditorReviewPackageService
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     product_id: int,
     *     product_name: string,
     *     shared_at: string|null,
     *     closed_at: string|null,
     *     evidence_count: int,
     *     findings_count: int,
     *     updated_at: string|null
     * }>
     */
    public function paginate(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'updated_at',
        string $sortOrder = 'desc',
        string $search = '',
        ?int $productId = null,
        ?AuditorReviewPackageStatus $status = null,
    ): LengthAwarePaginator {
        $query = AuditorReviewPackage::query()
            ->where('organization_id', $organization->id)
            ->with(['product:id,name'])
            ->withCount(['evidence', 'findings']);

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', fn($q) => $q->where('name', 'like', "%{$search}%"));

                if (ctype_digit($search)) {
                    $builder->orWhere('auditor_review_packages.id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'title' => 'title',
            'status' => 'status',
            'shared_at' => 'shared_at',
            'closed_at' => 'closed_at',
            'product_name' => 'product_id',
            default => 'updated_at',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(AuditorReviewPackage $package) => $this->listItemPayload($package));
    }

    /**
     * @param  array{
     *     product_id: int,
     *     title: string,
     *     notes?: string|null,
     *     evidence_ids?: list<int>
     * }  $attributes
     */
    public function create(Organization $organization, array $attributes, User $actor): AuditorReviewPackage
    {
        $product = $this->assertProductInOrganization($organization, (int) $attributes['product_id']);
        $evidenceIds = $this->filterEvidenceIds($product, $attributes['evidence_ids'] ?? []);

        return DB::transaction(function () use ($organization, $attributes, $actor, $product, $evidenceIds) {
            $package = AuditorReviewPackage::query()->create([
                'organization_id' => $organization->id,
                'product_id' => $product->id,
                'title' => trim((string) $attributes['title']),
                'status' => AuditorReviewPackageStatus::Draft,
                'created_by' => $actor->id,
                'notes' => $this->nullableString($attributes['notes'] ?? null),
            ]);

            $package->evidence()->sync($evidenceIds);

            AuditLogger::logAuditorPackageCreated($package, $actor);

            return $package->fresh(['product', 'evidence', 'creator']);
        });
    }

    /**
     * @param  array{
     *     title: string,
     *     notes?: string|null,
     *     evidence_ids?: list<int>
     * }  $attributes
     */
    public function update(AuditorReviewPackage $package, array $attributes, User $actor): AuditorReviewPackage
    {
        if (!$package->isEditable()) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.only_draft_editable'),
            ]);
        }

        $evidenceIds = $this->filterEvidenceIds($package->product, $attributes['evidence_ids'] ?? []);

        return DB::transaction(function () use ($package, $attributes, $actor, $evidenceIds) {
            $package->update([
                'title' => trim((string) $attributes['title']),
                'notes' => $this->nullableString($attributes['notes'] ?? null),
            ]);

            $package->evidence()->sync($evidenceIds);

            AuditLogger::logAuditorPackageUpdated($package->fresh(), $actor);

            return $package->fresh(['product', 'evidence', 'creator']);
        });
    }

    public function delete(AuditorReviewPackage $package, User $actor): void
    {
        if ($package->status !== AuditorReviewPackageStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.only_draft_deletable'),
            ]);
        }

        AuditLogger::logAuditorPackageDeleted($package, $actor);
        $package->delete();
    }

    public function share(AuditorReviewPackage $package, User $actor): AuditorReviewPackage
    {
        if ($package->status !== AuditorReviewPackageStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.only_draft_shareable'),
            ]);
        }

        $package->update([
            'status' => AuditorReviewPackageStatus::Shared,
            'shared_at' => now(),
            'closed_at' => null,
        ]);

        $package = $package->fresh(['product', 'evidence', 'creator']);
        AuditLogger::logAuditorPackageShared($package, $actor);

        return $package;
    }

    public function close(AuditorReviewPackage $package, User $actor): AuditorReviewPackage
    {
        if ($package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.only_shared_closable'),
            ]);
        }

        $package->update([
            'status' => AuditorReviewPackageStatus::Closed,
            'closed_at' => now(),
        ]);

        $package = $package->fresh(['product', 'evidence', 'creator']);
        AuditLogger::logAuditorPackageClosed($package, $actor);

        return $package;
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     product_id: int,
     *     product_name: string,
     *     shared_at: string|null,
     *     closed_at: string|null,
     *     evidence_count: int,
     *     findings_count: int,
     *     updated_at: string|null
     * }
     */
    public function listItemPayload(AuditorReviewPackage $package): array
    {
        return [
            'id' => $package->id,
            'title' => $package->title,
            'status' => $package->status->value,
            'product_id' => $package->product_id,
            'product_name' => $package->product?->name ?? '',
            'shared_at' => $package->shared_at?->toIso8601String(),
            'closed_at' => $package->closed_at?->toIso8601String(),
            'evidence_count' => (int) ($package->evidence_count ?? $package->evidence()->count()),
            'findings_count' => (int) ($package->findings_count ?? $package->findings()->count()),
            'updated_at' => $package->updated_at?->toIso8601String(),
        ];
    }

    private function assertProductInOrganization(Organization $organization, int $productId): Product
    {
        $product = Product::query()
            ->where('organization_id', $organization->id)
            ->where('id', $productId)
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => Translations::get('auditor.product_invalid'),
            ]);
        }

        return $product;
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function filterEvidenceIds(Product $product, array $ids): array
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));

        if ($normalized === []) {
            return [];
        }

        return Evidence::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $normalized)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}

<?php

namespace App\Services;

use App\Enums\AuditorReviewPackageStatus;
use App\Enums\RoleSlug;
use App\Jobs\SendAuditorReviewPackageSharedNotificationJob;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
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
        bool $includeDrafts = true,
    ): LengthAwarePaginator {
        $query = AuditorReviewPackage::query()
            ->where('organization_id', $organization->id)
            ->with(['product:id,name'])
            ->withCount(['evidence', 'findings']);

        if (!$includeDrafts) {
            $query->where('status', '!=', AuditorReviewPackageStatus::Draft->value);
        }

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

    /**
     * @return array{
     *     package: AuditorReviewPackage,
     *     notifications_queued: int,
     *     notifications_skipped: int,
     *     notifications_enabled: bool
     * }
     */
    public function share(AuditorReviewPackage $package, User $actor): array
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

        $package = $package->fresh(['product', 'evidence', 'creator', 'organization']);
        AuditLogger::logAuditorPackageShared($package, $actor);

        $notify = $this->queueShareNotifications($package, $actor);

        return [
            'package' => $package,
            'notifications_queued' => $notify['queued'],
            'notifications_skipped' => $notify['skipped_no_email'],
            'notifications_enabled' => $notify['enabled'],
        ];
    }

    /**
     * Queue stub emails to organization members with the auditor role.
     *
     * @return array{queued: int, skipped_no_email: int, enabled: bool}
     */
    public function queueShareNotifications(AuditorReviewPackage $package, User $actor): array
    {
        if (!config('auditor_notifications.enabled')) {
            return [
                'queued' => 0,
                'skipped_no_email' => 0,
                'enabled' => false,
            ];
        }

        $auditorRoleId = Role::query()
            ->where('slug', RoleSlug::Auditor->value)
            ->value('id');

        if ($auditorRoleId === null) {
            return [
                'queued' => 0,
                'skipped_no_email' => 0,
                'enabled' => true,
            ];
        }

        $organization = $package->organization
            ?? Organization::query()->find($package->organization_id);

        if ($organization === null) {
            return [
                'queued' => 0,
                'skipped_no_email' => 0,
                'enabled' => true,
            ];
        }

        $auditors = $organization->users()
            ->wherePivot('role_id', $auditorRoleId)
            ->get(['users.id', 'users.email']);

        $queued = 0;
        $skippedNoEmail = 0;

        foreach ($auditors as $auditor) {
            if (blank($auditor->email)) {
                $skippedNoEmail++;

                continue;
            }

            SendAuditorReviewPackageSharedNotificationJob::dispatch(
                $package->id,
                $auditor->id,
                $actor->id,
            );

            $queued++;
        }

        AuditLogger::logAuditorPackageNotificationsQueued(
            $package,
            $actor,
            $queued,
            $skippedNoEmail,
        );

        return [
            'queued' => $queued,
            'skipped_no_email' => $skippedNoEmail,
            'enabled' => true,
        ];
    }

    public function close(AuditorReviewPackage $package, User $actor): AuditorReviewPackage
    {
        if ($package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.only_shared_closable'),
            ]);
        }

        $this->clearGuestLink($package);

        $package->update([
            'status' => AuditorReviewPackageStatus::Closed,
            'closed_at' => now(),
        ]);

        $package = $package->fresh(['product', 'evidence', 'creator']);
        AuditLogger::logAuditorPackageClosed($package, $actor);

        return $package;
    }

    /**
     * @return array{package: AuditorReviewPackage, url: string, expires_at: string}
     */
    public function generateGuestLink(AuditorReviewPackage $package, User $actor, ?int $ttlDays = null): array
    {
        if ($package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.guest_link_only_shared'),
            ]);
        }

        $days = $ttlDays ?? (int) config('auditor_guest_access.guest_link_ttl_days', 7);
        $days = max(1, $days);
        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = now()->addDays($days);

        $package->update([
            'guest_token_hash' => hash('sha256', $plainToken),
            'guest_token_expires_at' => $expiresAt,
            'guest_token_created_at' => now(),
            'guest_token_created_by' => $actor->id,
            'guest_token_last_accessed_at' => null,
        ]);

        $package = $package->fresh(['product', 'creator']);

        AuditLogger::logAuditorPackageGuestLinkGenerated(
            $package,
            $actor,
            $expiresAt->toIso8601String(),
        );

        return [
            'package' => $package,
            'url' => route('auditor.guest.show', ['token' => $plainToken]),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function revokeGuestLink(AuditorReviewPackage $package, User $actor): AuditorReviewPackage
    {
        if (!filled($package->guest_token_hash)) {
            throw ValidationException::withMessages([
                'guest_link' => Translations::get('auditor.guest_link_missing'),
            ]);
        }

        $this->clearGuestLink($package);
        $package = $package->fresh(['product', 'creator']);

        AuditLogger::logAuditorPackageGuestLinkRevoked($package, $actor);

        return $package;
    }

    public function findPackageByGuestToken(string $token): ?AuditorReviewPackage
    {
        if ($token === '' || strlen($token) > 128) {
            return null;
        }

        $package = AuditorReviewPackage::query()
            ->where('guest_token_hash', hash('sha256', $token))
            ->first();

        if ($package === null || !$package->hasActiveGuestLink()) {
            return null;
        }

        return $package;
    }

    public function touchGuestLinkAccess(AuditorReviewPackage $package): void
    {
        $package->update([
            'guest_token_last_accessed_at' => now(),
        ]);
    }

    /**
     * @return array{active: bool, expires_at: string|null, created_at: string|null, last_accessed_at: string|null}
     */
    public function guestLinkPayload(AuditorReviewPackage $package): array
    {
        return [
            'active' => $package->hasActiveGuestLink(),
            'expires_at' => $package->guest_token_expires_at?->toIso8601String(),
            'created_at' => $package->guest_token_created_at?->toIso8601String(),
            'last_accessed_at' => $package->guest_token_last_accessed_at?->toIso8601String(),
        ];
    }

    private function clearGuestLink(AuditorReviewPackage $package): void
    {
        $package->forceFill([
            'guest_token_hash' => null,
            'guest_token_expires_at' => null,
            'guest_token_created_at' => null,
            'guest_token_created_by' => null,
            'guest_token_last_accessed_at' => null,
        ])->save();
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

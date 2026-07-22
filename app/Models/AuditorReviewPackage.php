<?php

namespace App\Models;

use App\Enums\AuditorReviewPackageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product-scoped auditor review package (passport / readiness / selected evidence).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property string $title
 * @property AuditorReviewPackageStatus $status
 * @property Carbon|null $shared_at
 * @property Carbon|null $closed_at
 * @property int $created_by
 * @property string|null $notes
 * @property string|null $guest_token_hash
 * @property Carbon|null $guest_token_expires_at
 * @property Carbon|null $guest_token_created_at
 * @property int|null $guest_token_created_by
 * @property Carbon|null $guest_token_last_accessed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Product|null $product
 * @property-read User|null $creator
 */
#[Fillable([
    'organization_id',
    'product_id',
    'title',
    'status',
    'shared_at',
    'closed_at',
    'created_by',
    'notes',
    'guest_token_hash',
    'guest_token_expires_at',
    'guest_token_created_at',
    'guest_token_created_by',
    'guest_token_last_accessed_at',
])]
class AuditorReviewPackage extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AuditorReviewPackageStatus::class,
            'shared_at' => 'datetime',
            'closed_at' => 'datetime',
            'guest_token_expires_at' => 'datetime',
            'guest_token_created_at' => 'datetime',
            'guest_token_last_accessed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<Evidence, $this> */
    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            Evidence::class,
            'auditor_review_package_evidence',
            'package_id',
            'evidence_id',
        )->withTimestamps();
    }

    /** @return HasMany<AuditorFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(AuditorFinding::class, 'package_id');
    }

    public function isEditable(): bool
    {
        return $this->status === AuditorReviewPackageStatus::Draft;
    }

    public function isShared(): bool
    {
        return $this->status === AuditorReviewPackageStatus::Shared;
    }

    public function isClosed(): bool
    {
        return $this->status === AuditorReviewPackageStatus::Closed;
    }

    public function hasActiveGuestLink(): bool
    {
        return filled($this->guest_token_hash)
            && $this->guest_token_expires_at !== null
            && $this->guest_token_expires_at->isFuture()
            && $this->status === AuditorReviewPackageStatus::Shared;
    }
}

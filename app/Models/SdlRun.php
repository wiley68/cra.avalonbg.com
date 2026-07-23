<?php

namespace App\Models;

use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Product-scoped Secure Development Lifecycle run (§5.14).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property string $title
 * @property SdlRunStatus $status
 * @property SdlStage $current_stage
 * @property int|null $owner_user_id
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Product|null $product
 * @property-read ProductVersion|null $version
 * @property-read User|null $owner
 * @property-read User|null $approver
 */
#[Fillable([
    'organization_id',
    'product_id',
    'product_version_id',
    'title',
    'status',
    'current_stage',
    'owner_user_id',
    'approved_at',
    'approved_by',
    'notes',
])]
class SdlRun extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SdlRunStatus::class,
            'current_stage' => SdlStage::class,
            'approved_at' => 'datetime',
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

    /** @return BelongsTo<ProductVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'product_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<SdlStageEntry, $this> */
    public function stageEntries(): HasMany
    {
        return $this->hasMany(SdlStageEntry::class, 'sdl_run_id');
    }

    /** @return BelongsToMany<Evidence, $this> */
    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            Evidence::class,
            'sdl_run_evidence',
            'sdl_run_id',
            'evidence_id',
        )->withTimestamps();
    }

    public function isActive(): bool
    {
        return in_array($this->status, SdlRunStatus::active(), true);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved() && $this->approved_at !== null;
    }

    /**
     * Ensure one stage entry exists for every fixed §5.14 stage (idempotent).
     */
    public function ensureStageEntries(): void
    {
        DB::transaction(function (): void {
            $existing = $this->stageEntries()
                ->pluck('stage')
                ->map(fn(SdlStage|string $stage): string => $stage instanceof SdlStage ? $stage->value : $stage)
                ->all();

            foreach (SdlStage::ordered() as $stage) {
                if (in_array($stage->value, $existing, true)) {
                    continue;
                }

                $this->stageEntries()->create([
                    'stage' => $stage,
                    'status' => SdlStageStatus::Pending,
                ]);
            }
        });
    }
}

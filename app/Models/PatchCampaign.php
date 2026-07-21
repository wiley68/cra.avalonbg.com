<?php

namespace App\Models;

use App\Enums\PatchCampaignStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property int $target_version_id
 * @property int|null $product_vulnerability_id
 * @property string $title
 * @property PatchCampaignStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'product_id',
    'target_version_id',
    'product_vulnerability_id',
    'title',
    'status',
    'started_at',
    'completed_at',
    'notes',
    'created_by',
])]
class PatchCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PatchCampaignStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function targetVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'target_version_id');
    }

    public function productVulnerability(): BelongsTo
    {
        return $this->belongsTo(ProductVulnerability::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PatchCampaignTarget::class, 'campaign_id');
    }
}

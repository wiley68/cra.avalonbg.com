<?php

namespace App\Models;

use App\Enums\RequirementApplicabilityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $requirement_id
 * @property int $requirement_version_id
 * @property RequirementApplicabilityStatus $status
 * @property string|null $rationale
 * @property int|null $owner_user_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'requirement_id',
    'requirement_version_id',
    'status',
    'rationale',
    'owner_user_id',
    'reviewed_by',
    'reviewed_at',
])]
class ProductRequirement extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RequirementApplicabilityStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function requirementVersion(): BelongsTo
    {
        return $this->belongsTo(RequirementVersion::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProductRequirementHistory::class);
    }
}

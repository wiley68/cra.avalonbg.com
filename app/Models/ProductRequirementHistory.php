<?php

namespace App\Models;

use App\Enums\RequirementApplicabilityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_requirement_id
 * @property RequirementApplicabilityStatus|null $from_status
 * @property RequirementApplicabilityStatus|string $to_status
 * @property string|null $rationale
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 */
#[Fillable([
    'product_requirement_id',
    'from_status',
    'to_status',
    'rationale',
    'changed_by',
])]
class ProductRequirementHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' => RequirementApplicabilityStatus::class,
            'to_status' => RequirementApplicabilityStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function productRequirement(): BelongsTo
    {
        return $this->belongsTo(ProductRequirement::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

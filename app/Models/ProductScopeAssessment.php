<?php

namespace App\Models;

use App\Enums\ScopeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property array<string, string> $answers
 * @property ScopeStatus $suggested_status
 * @property ScopeStatus $final_status
 * @property string|null $rationale
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'answers',
    'suggested_status',
    'final_status',
    'rationale',
    'reviewed_by',
    'reviewed_at',
])]
class ProductScopeAssessment extends Model
{
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'suggested_status' => ScopeStatus::class,
            'final_status' => ScopeStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

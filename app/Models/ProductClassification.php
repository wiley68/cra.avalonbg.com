<?php

namespace App\Models;

use App\Enums\ClassificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property array<string, string> $answers
 * @property ClassificationStatus $suggested_status
 * @property ClassificationStatus $final_status
 * @property string|null $rationale
 * @property string $regulatory_content_version
 * @property string|null $evidence_notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $next_review_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'answers',
    'suggested_status',
    'final_status',
    'rationale',
    'regulatory_content_version',
    'evidence_notes',
    'reviewed_by',
    'reviewed_at',
    'approved_by',
    'approved_at',
    'next_review_at',
])]
class ProductClassification extends Model
{
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'suggested_status' => ClassificationStatus::class,
            'final_status' => ClassificationStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'next_review_at' => 'date',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

<?php

namespace App\Models;

use App\Enums\SupportPeriodType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property SupportPeriodType $type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $basis
 * @property bool $is_extended
 * @property string|null $exceptions_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'type',
    'starts_at',
    'ends_at',
    'basis',
    'is_extended',
    'exceptions_notes',
])]
class ProductSupportPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SupportPeriodType::class,
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_extended' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVersion::class,
            'product_support_period_version',
        )->withTimestamps();
    }

    public function isActive(?Carbon $on = null): bool
    {
        $on ??= now()->startOfDay();

        return $this->starts_at->lte($on) && $this->ends_at->gte($on);
    }

    public function daysUntilEnd(?Carbon $on = null): int
    {
        $on ??= now()->startOfDay();

        return (int) $on->diffInDays($this->ends_at, false);
    }
}

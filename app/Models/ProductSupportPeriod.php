<?php

namespace App\Models;

use App\Enums\SupportPeriodStartBasis;
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
 * @property SupportPeriodStartBasis $start_basis
 * @property int $duration_months
 * @property string|null $basis
 * @property bool $is_extended
 * @property string|null $exceptions_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'type',
    'start_basis',
    'duration_months',
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
            'start_basis' => SupportPeriodStartBasis::class,
            'duration_months' => 'integer',
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

    public function scheduleResolved(): bool
    {
        return $this->effectiveStartsAt() !== null && $this->effectiveEndsAt() !== null;
    }

    public function effectiveStartsAt(): ?Carbon
    {
        if ($this->start_basis !== SupportPeriodStartBasis::ReleaseDate) {
            return null;
        }

        if (!$this->relationLoaded('versions')) {
            $this->load('versions:id,release_date');
        }

        $dates = $this->versions
            ->pluck('release_date')
            ->filter()
            ->map(fn($date) => $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay())
            ->sort()
            ->values();

        return $dates->first();
    }

    public function effectiveEndsAt(): ?Carbon
    {
        $startsAt = $this->effectiveStartsAt();

        if ($startsAt === null) {
            return null;
        }

        return $startsAt->copy()
            ->addMonthsNoOverflow(max(1, $this->duration_months))
            ->subDay()
            ->startOfDay();
    }

    public function isActive(?Carbon $on = null): ?bool
    {
        $startsAt = $this->effectiveStartsAt();
        $endsAt = $this->effectiveEndsAt();

        if ($startsAt === null || $endsAt === null) {
            return null;
        }

        $on ??= now()->startOfDay();

        return $startsAt->lte($on) && $endsAt->gte($on);
    }

    public function daysUntilEnd(?Carbon $on = null): ?int
    {
        $endsAt = $this->effectiveEndsAt();

        if ($endsAt === null) {
            return null;
        }

        $on ??= now()->startOfDay();

        return (int) $on->diffInDays($endsAt, false);
    }
}

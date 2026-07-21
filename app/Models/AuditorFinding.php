<?php

namespace App\Models;

use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Finding recorded against an auditor review package.
 *
 * @property int $id
 * @property int $package_id
 * @property AuditorFindingSeverity $severity
 * @property AuditorFindingStatus $status
 * @property string $title
 * @property string $body
 * @property int $created_by
 * @property Carbon|null $remediated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AuditorReviewPackage|null $package
 * @property-read User|null $creator
 */
#[Fillable([
    'package_id',
    'severity',
    'status',
    'title',
    'body',
    'created_by',
    'remediated_at',
])]
class AuditorFinding extends Model
{
    protected function casts(): array
    {
        return [
            'severity' => AuditorFindingSeverity::class,
            'status' => AuditorFindingStatus::class,
            'remediated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AuditorReviewPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(AuditorReviewPackage::class, 'package_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

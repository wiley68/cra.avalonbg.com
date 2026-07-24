<?php

namespace App\Models;

use App\Enums\IntegrationSyncRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $link_id
 * @property IntegrationSyncRunStatus $status
 * @property int|null $triggered_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductIntegrationLink $link
 * @property-read User|null $triggeredByUser
 */
#[Fillable([
    'link_id',
    'status',
    'triggered_by',
    'started_at',
    'finished_at',
    'summary',
])]
class IntegrationSyncRun extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IntegrationSyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    /** @return BelongsTo<ProductIntegrationLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(ProductIntegrationLink::class, 'link_id');
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}

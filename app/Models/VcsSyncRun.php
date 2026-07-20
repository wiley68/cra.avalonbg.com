<?php

namespace App\Models;

use App\Enums\VcsSyncRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $repository_id
 * @property VcsSyncRunStatus $status
 * @property int|null $triggered_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductRepository $repository
 * @property-read User|null $triggeredByUser
 */
#[Fillable([
    'repository_id',
    'status',
    'triggered_by',
    'started_at',
    'finished_at',
    'summary',
])]
class VcsSyncRun extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VcsSyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    /** @return BelongsTo<ProductRepository, $this> */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(ProductRepository::class, 'repository_id');
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}

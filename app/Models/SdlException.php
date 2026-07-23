<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Documented SDL stage exception with owner and review expiry.
 *
 * @property int $id
 * @property int $sdl_stage_entry_id
 * @property int $owner_user_id
 * @property Carbon $expires_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SdlStageEntry|null $stageEntry
 * @property-read User|null $owner
 * @property-read User|null $creator
 */
#[Fillable([
    'sdl_stage_entry_id',
    'owner_user_id',
    'expires_at',
    'created_by',
])]
class SdlException extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
        ];
    }

    /** @return BelongsTo<SdlStageEntry, $this> */
    public function stageEntry(): BelongsTo
    {
        return $this->belongsTo(SdlStageEntry::class, 'sdl_stage_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Task, $this> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->endOfDay()->isPast();
    }
}

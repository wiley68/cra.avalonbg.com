<?php

namespace App\Models;

use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Fixed-stage checklist entry for an SDL run.
 *
 * @property int $id
 * @property int $sdl_run_id
 * @property SdlStage $stage
 * @property SdlStageStatus $status
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SdlRun|null $run
 * @property-read User|null $completer
 */
#[Fillable([
    'sdl_run_id',
    'stage',
    'status',
    'completed_at',
    'completed_by',
    'notes',
])]
class SdlStageEntry extends Model
{
    protected function casts(): array
    {
        return [
            'stage' => SdlStage::class,
            'status' => SdlStageStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SdlRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(SdlRun::class, 'sdl_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return BelongsToMany<Evidence, $this> */
    public function evidence(): BelongsToMany
    {
        return $this->belongsToMany(
            Evidence::class,
            'sdl_stage_evidence',
            'sdl_stage_entry_id',
            'evidence_id',
        )->withTimestamps();
    }

    public function isComplete(): bool
    {
        return $this->status->isComplete();
    }
}

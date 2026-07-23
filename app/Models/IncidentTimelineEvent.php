<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-oriented timeline entry for a product incident.
 *
 * @property int $id
 * @property int $incident_id
 * @property Carbon $occurred_at
 * @property string $label
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductIncident|null $incident
 * @property-read User|null $creator
 */
#[Fillable([
    'incident_id',
    'occurred_at',
    'label',
    'notes',
    'created_by',
])]
class IncidentTimelineEvent extends Model
{
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProductIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(ProductIncident::class, 'incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

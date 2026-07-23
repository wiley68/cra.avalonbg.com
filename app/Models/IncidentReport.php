<?php

namespace App\Models;

use App\Enums\IncidentReportChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Manual authority submission record for a product incident (append-only).
 *
 * @property int $id
 * @property int $incident_id
 * @property string $authority
 * @property Carbon $submitted_at
 * @property int|null $submitted_by
 * @property IncidentReportChannel $submission_channel
 * @property string|null $submission_reference
 * @property string|null $summary
 * @property string|null $notes
 * @property int|null $evidence_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductIncident|null $incident
 * @property-read User|null $submitter
 * @property-read Evidence|null $evidence
 */
#[Fillable([
    'incident_id',
    'authority',
    'submitted_at',
    'submitted_by',
    'submission_channel',
    'submission_reference',
    'summary',
    'notes',
    'evidence_id',
])]
class IncidentReport extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'submission_channel' => IncidentReportChannel::class,
        ];
    }

    /** @return BelongsTo<ProductIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(ProductIncident::class, 'incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<Evidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}

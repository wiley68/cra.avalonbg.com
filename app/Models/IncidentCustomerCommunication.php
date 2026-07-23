<?php

namespace App\Models;

use App\Enums\IncidentCommunicationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Manual customer communication record for a product incident (append-only).
 * Not related to patch campaign notification logs.
 *
 * @property int $id
 * @property int $incident_id
 * @property Carbon $communicated_at
 * @property int|null $recorded_by
 * @property IncidentCommunicationChannel $channel
 * @property int|null $customer_id
 * @property string|null $audience
 * @property string $subject
 * @property string|null $summary
 * @property string|null $notes
 * @property int|null $evidence_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductIncident|null $incident
 * @property-read User|null $recorder
 * @property-read Customer|null $customer
 * @property-read Evidence|null $evidence
 */
#[Fillable([
    'incident_id',
    'communicated_at',
    'recorded_by',
    'channel',
    'customer_id',
    'audience',
    'subject',
    'summary',
    'notes',
    'evidence_id',
])]
class IncidentCustomerCommunication extends Model
{
    protected function casts(): array
    {
        return [
            'communicated_at' => 'datetime',
            'channel' => IncidentCommunicationChannel::class,
        ];
    }

    /** @return BelongsTo<ProductIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(ProductIncident::class, 'incident_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Evidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }
}

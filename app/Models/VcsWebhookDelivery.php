<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property string $delivery_id
 * @property string $event
 * @property int|null $repository_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'connection_id',
    'delivery_id',
    'event',
    'repository_id',
    'status',
])]
class VcsWebhookDelivery extends Model
{
    /** @return BelongsTo<OrganizationVcsConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(OrganizationVcsConnection::class, 'connection_id');
    }
}

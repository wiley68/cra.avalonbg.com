<?php

namespace App\Models;

use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property VcsProvider $provider
 * @property VcsAuthType $auth_type
 * @property string $token
 * @property string|null $label
 * @property VcsConnectionStatus $status
 * @property VcsSyncSchedule $sync_schedule
 * @property string|null $webhook_secret
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'provider',
    'auth_type',
    'token',
    'label',
    'status',
    'sync_schedule',
    'webhook_secret',
    'last_verified_at',
])]
#[Hidden(['token', 'webhook_secret'])]
class OrganizationVcsConnection extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => VcsProvider::class,
            'auth_type' => VcsAuthType::class,
            'token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'status' => VcsConnectionStatus::class,
            'sync_schedule' => VcsSyncSchedule::class,
            'last_verified_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(ProductRepository::class, 'connection_id');
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(VcsWebhookDelivery::class, 'connection_id');
    }
}

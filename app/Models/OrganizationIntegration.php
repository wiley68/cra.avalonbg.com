<?php

namespace App\Models;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncSchedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property IntegrationProvider $provider
 * @property IntegrationCategory $category
 * @property IntegrationAuthType $auth_type
 * @property array<string, mixed>|null $credentials
 * @property string|null $label
 * @property IntegrationConnectionStatus $status
 * @property IntegrationSyncSchedule $sync_schedule
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductIntegrationLink> $links
 */
#[Fillable([
    'organization_id',
    'provider',
    'category',
    'auth_type',
    'credentials',
    'label',
    'status',
    'sync_schedule',
    'last_verified_at',
])]
#[Hidden(['credentials'])]
class OrganizationIntegration extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'category' => IntegrationCategory::class,
            'auth_type' => IntegrationAuthType::class,
            'credentials' => 'encrypted:array',
            'status' => IntegrationConnectionStatus::class,
            'sync_schedule' => IntegrationSyncSchedule::class,
            'last_verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<ProductIntegrationLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(ProductIntegrationLink::class, 'integration_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $integration_id
 * @property string|null $external_project_key
 * @property string|null $external_target_id
 * @property string|null $external_label
 * @property array<string, mixed>|null $config
 * @property Carbon|null $last_synced_at
 * @property array<string, mixed>|null $last_sync_summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read OrganizationIntegration $integration
 * @property-read \Illuminate\Database\Eloquent\Collection<int, IntegrationSyncRun> $syncRuns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ImportSuggestion> $importSuggestions
 */
#[Fillable([
    'product_id',
    'integration_id',
    'external_project_key',
    'external_target_id',
    'external_label',
    'config',
    'last_synced_at',
    'last_sync_summary',
])]
class ProductIntegrationLink extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_synced_at' => 'datetime',
            'last_sync_summary' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<OrganizationIntegration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(OrganizationIntegration::class, 'integration_id');
    }

    /** @return HasMany<IntegrationSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(IntegrationSyncRun::class, 'link_id');
    }

    /** @return HasMany<ImportSuggestion, $this> */
    public function importSuggestions(): HasMany
    {
        return $this->hasMany(ImportSuggestion::class, 'link_id');
    }
}

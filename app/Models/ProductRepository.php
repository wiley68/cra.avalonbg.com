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
 * @property int $connection_id
 * @property string|null $external_id
 * @property string $full_name
 * @property string $remote_url
 * @property string|null $default_branch
 * @property Carbon|null $last_synced_at
 * @property array<string, mixed>|null $last_sync_summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read OrganizationVcsConnection $connection
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VcsSyncRun> $syncRuns
 */
#[Fillable([
    'product_id',
    'connection_id',
    'external_id',
    'full_name',
    'remote_url',
    'default_branch',
    'last_synced_at',
    'last_sync_summary',
])]
class ProductRepository extends Model
{
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_sync_summary' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<OrganizationVcsConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(OrganizationVcsConnection::class, 'connection_id');
    }

    /** @return HasMany<VcsSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(VcsSyncRun::class, 'repository_id');
    }
}

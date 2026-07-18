<?php

namespace App\Models;

use App\Enums\ComponentSupportStatus;
use App\Enums\PackageEcosystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $product_version_id
 * @property int|null $sbom_id
 * @property string $name
 * @property string|null $supplier
 * @property PackageEcosystem $package_ecosystem
 * @property string|null $version
 * @property string|null $licence
 * @property string|null $purl
 * @property string|null $hash
 * @property bool $is_direct
 * @property bool $is_dev
 * @property string|null $usage_context
 * @property ComponentSupportStatus $support_status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'product_version_id',
    'sbom_id',
    'name',
    'supplier',
    'package_ecosystem',
    'version',
    'licence',
    'purl',
    'hash',
    'is_direct',
    'is_dev',
    'usage_context',
    'support_status',
    'notes',
])]
class ProductComponent extends Model
{
    protected function casts(): array
    {
        return [
            'package_ecosystem' => PackageEcosystem::class,
            'support_status' => ComponentSupportStatus::class,
            'is_direct' => 'boolean',
            'is_dev' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    public function sbom(): BelongsTo
    {
        return $this->belongsTo(Sbom::class);
    }
}

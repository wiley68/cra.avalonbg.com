<?php

namespace App\Models;

use App\Enums\SbomFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $product_version_id
 * @property SbomFormat $format
 * @property string $source_filename
 * @property string|null $storage_path
 * @property string $checksum_sha256
 * @property int $component_count
 * @property int|null $imported_by
 * @property Carbon $imported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'product_version_id',
    'format',
    'source_filename',
    'storage_path',
    'checksum_sha256',
    'component_count',
    'imported_by',
    'imported_at',
])]
class Sbom extends Model
{
    protected function casts(): array
    {
        return [
            'format' => SbomFormat::class,
            'component_count' => 'integer',
            'imported_at' => 'datetime',
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

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class);
    }
}

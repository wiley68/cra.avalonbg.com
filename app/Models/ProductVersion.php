<?php

namespace App\Models;

use App\Enums\ProductVersionState;
use App\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $version_number
 * @property Carbon|null $release_date
 * @property ProductVersionState $state
 * @property SupportStatus $support_status
 * @property Carbon|null $security_support_deadline
 * @property string|null $git_ref
 * @property string|null $build_identifier
 * @property string|null $artifact_hash
 * @property string|null $changelog
 * @property int|null $previous_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'version_number',
    'release_date',
    'state',
    'support_status',
    'security_support_deadline',
    'git_ref',
    'build_identifier',
    'artifact_hash',
    'changelog',
    'previous_version_id',
])]
class ProductVersion extends Model
{
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'state' => ProductVersionState::class,
            'support_status' => SupportStatus::class,
            'security_support_deadline' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class, 'previous_version_id');
    }
}

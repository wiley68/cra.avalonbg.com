<?php

namespace App\Models;

use App\Enums\ProductControlStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $control_id
 * @property ProductControlStatus $status
 * @property string|null $notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'control_id',
    'status',
    'notes',
    'reviewed_by',
    'reviewed_at',
])]
class ProductControl extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ProductControlStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

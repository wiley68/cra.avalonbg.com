<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $regulation_id
 * @property string $code
 * @property string|null $article_ref
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'regulation_id',
    'code',
    'article_ref',
    'sort_order',
    'is_active',
])]
class Requirement extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RequirementVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(RequirementVersion::class)->where('is_current', true);
    }

    public function productRequirements(): HasMany
    {
        return $this->hasMany(ProductRequirement::class);
    }
}

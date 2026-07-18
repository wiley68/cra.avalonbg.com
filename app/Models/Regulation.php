<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $jurisdiction
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'title',
    'jurisdiction',
])]
class Regulation extends Model
{
    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }
}

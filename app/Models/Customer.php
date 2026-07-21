<?php

namespace App\Models;

use App\Enums\CustomerCriticality;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $external_ref
 * @property string|null $primary_contact
 * @property CustomerCriticality $criticality
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'name',
    'external_ref',
    'primary_contact',
    'criticality',
    'notes',
    'is_active',
])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'criticality' => CustomerCriticality::class,
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(ProductDeployment::class);
    }
}

<?php

namespace App\Models;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $owner_user_id
 * @property string|null $implementation_guidance
 * @property ControlAutomationLevel $automation_level
 * @property ControlFrequency $frequency
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'description',
    'owner_user_id',
    'implementation_guidance',
    'automation_level',
    'frequency',
    'is_active',
])]
class Control extends Model
{
    protected function casts(): array
    {
        return [
            'automation_level' => ControlAutomationLevel::class,
            'frequency' => ControlFrequency::class,
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class)
            ->withTimestamps();
    }

    public function productControls(): HasMany
    {
        return $this->hasMany(ProductControl::class);
    }
}

<?php

namespace App\Models;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Enums\ControlSource;
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
 * @property string|null $name_bg
 * @property string|null $description
 * @property string|null $description_bg
 * @property int|null $owner_user_id
 * @property string|null $implementation_guidance
 * @property string|null $implementation_guidance_bg
 * @property ControlAutomationLevel $automation_level
 * @property ControlFrequency $frequency
 * @property bool $is_active
 * @property ControlSource $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'code',
    'name',
    'name_bg',
    'description',
    'description_bg',
    'owner_user_id',
    'implementation_guidance',
    'implementation_guidance_bg',
    'automation_level',
    'frequency',
    'is_active',
    'source',
])]
class Control extends Model
{
    /**
     * @var list<string>
     */
    public const LOCALIZABLE_FIELDS = [
        'name',
        'description',
        'implementation_guidance',
    ];

    protected function casts(): array
    {
        return [
            'automation_level' => ControlAutomationLevel::class,
            'frequency' => ControlFrequency::class,
            'is_active' => 'boolean',
            'source' => ControlSource::class,
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

    public function localized(string $field, ?string $locale = null): ?string
    {
        if (!in_array($field, self::LOCALIZABLE_FIELDS, true)) {
            return null;
        }

        $locale ??= app()->getLocale();
        $fallback = $this->{$field};

        if ($locale === 'bg') {
            $translated = $this->{"{$field}_bg"};

            if (filled($translated)) {
                return $translated;
            }
        }

        return $fallback;
    }
}

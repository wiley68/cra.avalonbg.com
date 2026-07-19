<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requirement_id
 * @property int $version
 * @property string $requirement_text
 * @property string|null $requirement_text_bg
 * @property string|null $plain_language
 * @property string|null $plain_language_bg
 * @property string|null $applicability_notes
 * @property string|null $applicability_notes_bg
 * @property string|null $suggested_controls_text
 * @property string|null $suggested_controls_text_bg
 * @property string|null $required_evidence_text
 * @property string|null $required_evidence_text_bg
 * @property Carbon|null $published_at
 * @property bool $is_current
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'requirement_id',
    'version',
    'requirement_text',
    'requirement_text_bg',
    'plain_language',
    'plain_language_bg',
    'applicability_notes',
    'applicability_notes_bg',
    'suggested_controls_text',
    'suggested_controls_text_bg',
    'required_evidence_text',
    'required_evidence_text_bg',
    'published_at',
    'is_current',
])]
class RequirementVersion extends Model
{
    /**
     * @var list<string>
     */
    public const LOCALIZABLE_FIELDS = [
        'requirement_text',
        'plain_language',
        'applicability_notes',
        'suggested_controls_text',
        'required_evidence_text',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function productRequirements(): HasMany
    {
        return $this->hasMany(ProductRequirement::class);
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

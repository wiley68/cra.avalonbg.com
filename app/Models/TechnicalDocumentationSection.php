<?php

namespace App\Models;

use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Section within a technical documentation package (§5.12).
 *
 * @property int $id
 * @property int $package_id
 * @property TechnicalDocumentationSectionKey $section_key
 * @property TechnicalDocumentationSectionSource $source
 * @property string|null $body_markdown
 * @property array<string, mixed>|null $generated_payload
 * @property int $sort_order
 * @property bool $is_applicable
 * @property string|null $override_reason
 * @property bool $changed_since_parent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TechnicalDocumentationPackage|null $package
 */
#[Fillable([
    'package_id',
    'section_key',
    'source',
    'body_markdown',
    'generated_payload',
    'sort_order',
    'is_applicable',
    'override_reason',
    'changed_since_parent',
])]
class TechnicalDocumentationSection extends Model
{
    protected function casts(): array
    {
        return [
            'section_key' => TechnicalDocumentationSectionKey::class,
            'source' => TechnicalDocumentationSectionSource::class,
            'generated_payload' => 'array',
            'is_applicable' => 'boolean',
            'changed_since_parent' => 'boolean',
        ];
    }

    /** @return BelongsTo<TechnicalDocumentationPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(TechnicalDocumentationPackage::class, 'package_id');
    }
}

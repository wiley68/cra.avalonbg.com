<?php

namespace App\Models;

use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $link_id
 * @property ImportSuggestionKind $kind
 * @property string $external_id
 * @property string $title
 * @property array<string, mixed>|null $payload
 * @property ImportSuggestionStatus $status
 * @property string|null $accepted_entity_type
 * @property int|null $accepted_entity_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read ProductIntegrationLink $link
 */
#[Fillable([
    'product_id',
    'link_id',
    'kind',
    'external_id',
    'title',
    'payload',
    'status',
    'accepted_entity_type',
    'accepted_entity_id',
])]
class ImportSuggestion extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => ImportSuggestionKind::class,
            'payload' => 'array',
            'status' => ImportSuggestionStatus::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductIntegrationLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(ProductIntegrationLink::class, 'link_id');
    }
}

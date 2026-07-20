<?php

namespace App\Models;

use App\Enums\VcsImportSuggestionKind;
use App\Enums\VcsImportSuggestionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $repository_id
 * @property VcsImportSuggestionKind $kind
 * @property string $external_id
 * @property array<string, mixed> $payload
 * @property VcsImportSuggestionStatus $status
 * @property string|null $accepted_entity_type
 * @property int|null $accepted_entity_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read ProductRepository $repository
 */
#[Fillable([
    'product_id',
    'repository_id',
    'kind',
    'external_id',
    'payload',
    'status',
    'accepted_entity_type',
    'accepted_entity_id',
])]
class VcsImportSuggestion extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => VcsImportSuggestionKind::class,
            'payload' => 'array',
            'status' => VcsImportSuggestionStatus::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductRepository, $this> */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(ProductRepository::class, 'repository_id');
    }
}

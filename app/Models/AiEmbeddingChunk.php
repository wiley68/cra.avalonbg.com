<?php

namespace App\Models;

use App\Enums\AiEmbeddingSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $product_id
 * @property AiEmbeddingSourceType $source_type
 * @property int $source_id
 * @property int $chunk_index
 * @property string $content
 * @property list<float> $embedding
 * @property string|null $embedding_model
 * @property int|null $dimensions
 * @property string $content_hash
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'product_id',
    'source_type',
    'source_id',
    'chunk_index',
    'content',
    'embedding',
    'embedding_model',
    'dimensions',
    'content_hash',
    'metadata',
])]
class AiEmbeddingChunk extends Model
{
    protected function casts(): array
    {
        return [
            'source_type' => AiEmbeddingSourceType::class,
            'embedding' => 'array',
            'metadata' => 'array',
            'chunk_index' => 'integer',
            'dimensions' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

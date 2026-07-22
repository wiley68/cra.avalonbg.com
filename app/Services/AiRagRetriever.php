<?php

namespace App\Services;

use App\Contracts\EmbeddingProvider;
use App\Models\AiEmbeddingChunk;
use App\Models\Product;
use App\Services\Ai\CosineSimilarity;

class AiRagRetriever
{
    public function __construct(
        private readonly EmbeddingProvider $embeddings,
        private readonly AiEmbeddingIndexer $indexer,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('ai.rag.enabled', true);
    }

    public function chunkCountForProduct(Product $product): int
    {
        return AiEmbeddingChunk::query()
            ->where('organization_id', $product->organization_id)
            ->where(function ($query) use ($product): void {
                $query->where('product_id', $product->id)
                    ->orWhereNull('product_id');
            })
            ->count();
    }

    /**
     * Ensure an index exists for the product (lazy first-use indexing).
     *
     * @return array{chunks: int, sources: int}|null
     */
    public function ensureIndexed(Product $product): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if ($this->chunkCountForProduct($product) > 0) {
            return null;
        }

        return $this->indexer->indexProduct($product);
    }

    /**
     * @return array{
     *     text: string,
     *     hits: int,
     *     passages: list<array{source_type: string, source_id: int, score: float, excerpt: string}>
     * }
     */
    public function retrieve(Product $product, string $query): array
    {
        if (!$this->isEnabled()) {
            return ['text' => '', 'hits' => 0, 'passages' => []];
        }

        $trimmed = trim($query);
        if ($trimmed === '') {
            return ['text' => '', 'hits' => 0, 'passages' => []];
        }

        $this->ensureIndexed($product);

        $topK = max(1, (int) config('ai.rag.top_k', 6));
        $minScore = (float) config('ai.rag.min_score', 0.05);
        $excerptChars = max(80, (int) config('ai.rag.passage_chars', 600));

        $queryVector = $this->embeddings->embed($trimmed);

        $candidates = AiEmbeddingChunk::query()
            ->where('organization_id', $product->organization_id)
            ->where(function ($q) use ($product): void {
                $q->where('product_id', $product->id)
                    ->orWhereNull('product_id');
            })
            ->orderBy('id')
            ->limit(max(50, (int) config('ai.rag.candidate_limit', 200)))
            ->get();

        if ($candidates->isEmpty()) {
            return ['text' => '', 'hits' => 0, 'passages' => []];
        }

        $scored = [];
        foreach ($candidates as $chunk) {
            /** @var list<float> $embedding */
            $embedding = is_array($chunk->embedding) ? $chunk->embedding : [];
            $score = CosineSimilarity::score($queryVector, $embedding);
            if ($score < $minScore) {
                continue;
            }

            $scored[] = [
                'chunk' => $chunk,
                'score' => $score,
            ];
        }

        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, $topK);

        if ($scored === []) {
            return ['text' => '', 'hits' => 0, 'passages' => []];
        }

        $lines = ['## Retrieved passages (RAG)', 'Human review required — passages are suggestions for grounding only.'];
        $passages = [];

        foreach ($scored as $row) {
            /** @var AiEmbeddingChunk $chunk */
            $chunk = $row['chunk'];
            $score = round((float) $row['score'], 4);
            $sourceType = $chunk->source_type->value;
            $excerpt = mb_strlen($chunk->content) <= $excerptChars
                ? $chunk->content
                : rtrim(mb_substr($chunk->content, 0, $excerptChars - 1)) . '…';

            $lines[] = '';
            $lines[] = "### [{$sourceType}#{$chunk->source_id}] score={$score}";
            $lines[] = $excerpt;

            $passages[] = [
                'source_type' => $sourceType,
                'source_id' => $chunk->source_id,
                'score' => $score,
                'excerpt' => $excerpt,
            ];
        }

        return [
            'text' => implode("\n", $lines),
            'hits' => count($passages),
            'passages' => $passages,
        ];
    }
}

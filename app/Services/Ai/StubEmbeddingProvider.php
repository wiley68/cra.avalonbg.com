<?php

namespace App\Services\Ai;

use App\Contracts\EmbeddingProvider;
use App\Enums\EmbeddingProviderDriver;

/**
 * Deterministic bag-of-tokens hashing into a fixed-size unit vector.
 * Enables cosine retrieval in tests/dev without an external embedding API.
 */
class StubEmbeddingProvider implements EmbeddingProvider
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $dims = $this->dimensions();
        $vector = array_fill(0, $dims, 0.0);

        $tokens = preg_split('/\W+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            $tokens = ['empty'];
        }

        foreach ($tokens as $token) {
            $hash = crc32((string) $token);
            $index = $hash % $dims;
            $sign = ($hash & 1) === 1 ? 1.0 : -1.0;
            $vector[$index] += $sign;
        }

        return $this->normalize($vector);
    }

    public function model(): string
    {
        return 'stub-hash-' . $this->dimensions();
    }

    public function dimensions(): int
    {
        return max(8, (int) config('ai.embeddings.dimensions', 64));
    }

    public function driver(): string
    {
        return EmbeddingProviderDriver::Stub->value;
    }

    /**
     * @param  list<float>  $vector
     * @return list<float>
     */
    private function normalize(array $vector): array
    {
        $sumSquares = 0.0;
        foreach ($vector as $value) {
            $sumSquares += $value * $value;
        }

        if ($sumSquares <= 0.0) {
            $vector[0] = 1.0;

            return $vector;
        }

        $norm = sqrt($sumSquares);

        return array_map(
            static fn(float $value): float => $value / $norm,
            $vector,
        );
    }
}

<?php

namespace App\Contracts;

interface EmbeddingProvider
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    public function model(): string;

    public function dimensions(): int;

    public function driver(): string;
}

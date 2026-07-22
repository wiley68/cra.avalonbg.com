<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiEmbeddingIndexer;
use Illuminate\Console\Command;

class IndexAiEmbeddingsCommand extends Command
{
    protected $signature = 'ai:index-embeddings
                            {product? : Product ID to index (omit to index all products)}
                            {--organization= : Limit to organization ID}';

    protected $description = 'Build or refresh the local AI embedding / RAG index for product workspace content';

    public function handle(AiEmbeddingIndexer $indexer): int
    {
        if (!(bool) config('ai.rag.enabled', true)) {
            $this->warn('RAG is disabled (CRA_AI_RAG_ENABLED=false). Nothing to index.');

            return self::SUCCESS;
        }

        $productId = $this->argument('product');
        $organizationId = $this->option('organization');

        $query = Product::query()->orderBy('id');

        if ($productId !== null && $productId !== '') {
            $query->whereKey((int) $productId);
        }

        if ($organizationId !== null && $organizationId !== '') {
            $query->where('organization_id', (int) $organizationId);
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $this->warn('No products matched.');

            return self::FAILURE;
        }

        $totalChunks = 0;
        $totalSources = 0;

        foreach ($products as $product) {
            $result = $indexer->indexProduct($product);
            $totalChunks += $result['chunks'];
            $totalSources += $result['sources'];
            $this->line("Product #{$product->id} ({$product->slug}): {$result['chunks']} chunk(s) from {$result['sources']} source(s)");
        }

        $this->info("Indexed {$products->count()} product(s): {$totalChunks} chunk(s), {$totalSources} source(s).");

        return self::SUCCESS;
    }
}

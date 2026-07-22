<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiEmbeddingIndexer;
use App\Services\AiQueuedAnalysisService;
use Illuminate\Console\Command;

class IndexAiEmbeddingsCommand extends Command
{
    protected $signature = 'ai:index-embeddings
                            {product? : Product ID to index (omit to index all products)}
                            {--organization= : Limit to organization ID}
                            {--sync : Run indexing inline instead of queueing}';

    protected $description = 'Build or refresh the local AI embedding / RAG index for product workspace content';

    public function handle(AiEmbeddingIndexer $indexer, AiQueuedAnalysisService $queued): int
    {
        if (!(bool) config('ai.rag.enabled', true)) {
            $this->warn('RAG is disabled (CRA_AI_RAG_ENABLED=false). Nothing to index.');

            return self::SUCCESS;
        }

        $productId = $this->argument('product');
        $organizationId = $this->option('organization');
        $sync = (bool) $this->option('sync');

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

        if (!$sync && $queued->queueEnabled()) {
            foreach ($products as $product) {
                $result = $queued->queueRagIndex($product);
                $this->line("Product #{$product->id} ({$product->slug}): queued job #{$result['analysis_job']->id}");
            }

            $this->info("Queued RAG index for {$products->count()} product(s). Run queue:work to process.");

            return self::SUCCESS;
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

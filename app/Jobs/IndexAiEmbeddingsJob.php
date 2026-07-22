<?php

namespace App\Jobs;

use App\Models\AiAnalysisJob;
use App\Services\AiQueuedAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexAiEmbeddingsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout;

    public int $uniqueFor = 600;

    public function __construct(
        public int $analysisJobId,
    ) {
        $this->timeout = max(60, (int) config('ai.queue.timeout', 120));
    }

    public function uniqueId(): string
    {
        $job = AiAnalysisJob::query()->find($this->analysisJobId);

        return 'ai-rag-index-' . ($job?->product_id ?? $this->analysisJobId);
    }

    public function handle(AiQueuedAnalysisService $queued): void
    {
        $job = AiAnalysisJob::query()->find($this->analysisJobId);
        if ($job === null) {
            return;
        }

        $queued->process($job);
    }
}

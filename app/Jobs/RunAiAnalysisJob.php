<?php

namespace App\Jobs;

use App\Models\AiAnalysisJob;
use App\Services\AiQueuedAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAiAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public function __construct(
        public int $analysisJobId,
    ) {
        $this->timeout = max(60, (int) config('ai.queue.timeout', 120));
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

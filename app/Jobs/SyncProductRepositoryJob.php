<?php

namespace App\Jobs;

use App\Models\ProductRepository;
use App\Models\User;
use App\Services\VcsSyncService;
use App\Support\QueuedSyncFailureRecorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncProductRepositoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Soft-fail HTTP paths do not throw; hard failures retry then land in failed_jobs. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(
        public int $repositoryId,
        public ?int $triggeredByUserId = null,
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->repositoryId;
    }

    public function handle(VcsSyncService $sync): void
    {
        $repository = ProductRepository::query()
            ->with(['connection', 'product'])
            ->findOrFail($this->repositoryId);

        $actor = $this->triggeredByUserId !== null
            ? User::query()->find($this->triggeredByUserId)
            : null;

        $sync->sync($repository, $actor);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        QueuedSyncFailureRecorder::recordProductRepository($this->repositoryId, $exception);
    }
}

<?php

namespace App\Jobs;

use App\Enums\IntegrationCategory;
use App\Enums\IntegrationProvider;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\AlmSyncService;
use App\Services\ScannerSyncService;
use App\Support\QueuedSyncFailureRecorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncProductIntegrationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Soft-fail HTTP paths do not throw; hard failures retry then land in failed_jobs. */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60, 120];

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(
        public int $linkId,
        public ?int $triggeredByUserId = null,
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->linkId;
    }

    public function handle(AlmSyncService $almSync, ScannerSyncService $scannerSync): void
    {
        $link = ProductIntegrationLink::query()
            ->with(['integration', 'product'])
            ->findOrFail($this->linkId);

        $actor = $this->triggeredByUserId !== null
            ? User::query()->find($this->triggeredByUserId)
            : null;

        if (
            $link->integration->provider === IntegrationProvider::Snyk
            || $link->integration->category === IntegrationCategory::Scanner
        ) {
            $scannerSync->sync($link, $actor);

            return;
        }

        $almSync->sync($link, $actor);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        QueuedSyncFailureRecorder::recordIntegrationLink($this->linkId, $exception);
    }
}

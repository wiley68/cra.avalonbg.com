<?php

namespace App\Jobs;

use App\Enums\IntegrationCategory;
use App\Enums\IntegrationProvider;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\AlmSyncService;
use App\Services\ScannerSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProductIntegrationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
}

<?php

namespace App\Console\Commands;

use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationSyncSchedule;
use App\Jobs\SyncProductIntegrationJob;
use App\Models\OrganizationIntegration;
use App\Models\ProductIntegrationLink;
use Illuminate\Console\Command;

class SyncScheduledIntegrations extends Command
{
    protected $signature = 'integrations:sync-scheduled';

    protected $description = 'Dispatch sync jobs for product integration links due by their connector schedule';

    public function handle(): int
    {
        $dispatched = 0;
        $skipped = 0;

        $integrations = OrganizationIntegration::query()
            ->where('status', IntegrationConnectionStatus::Active)
            ->whereIn('sync_schedule', [
                IntegrationSyncSchedule::Hourly->value,
                IntegrationSyncSchedule::Daily->value,
            ])
            ->whereHas('organization', fn($query) => $query->where('is_active', true))
            ->with(['links' => fn($query) => $query->orderBy('id')])
            ->get();

        foreach ($integrations as $integration) {
            foreach ($integration->links as $link) {
                if (!$this->shouldSync($integration, $link)) {
                    $skipped++;

                    continue;
                }

                SyncProductIntegrationJob::dispatch($link->id);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} integration sync job(s); skipped {$skipped} not due.");

        return self::SUCCESS;
    }

    private function shouldSync(OrganizationIntegration $integration, ProductIntegrationLink $link): bool
    {
        return $integration->sync_schedule->isDue($link->last_synced_at);
    }
}

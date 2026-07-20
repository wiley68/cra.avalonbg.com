<?php

namespace App\Console\Commands;

use App\Enums\VcsConnectionStatus;
use App\Enums\VcsSyncSchedule;
use App\Jobs\SyncProductRepositoryJob;
use App\Models\OrganizationVcsConnection;
use App\Models\ProductRepository;
use Illuminate\Console\Command;

class SyncScheduledVcsRepositories extends Command
{
    protected $signature = 'vcs:sync-scheduled';

    protected $description = 'Dispatch sync jobs for product repositories due by their connection schedule';

    public function handle(): int
    {
        $dispatched = 0;
        $skipped = 0;

        $connections = OrganizationVcsConnection::query()
            ->where('status', VcsConnectionStatus::Active)
            ->whereIn('sync_schedule', [
                VcsSyncSchedule::Hourly->value,
                VcsSyncSchedule::Daily->value,
            ])
            ->whereHas('organization', fn($query) => $query->where('is_active', true))
            ->with(['repositories' => fn($query) => $query->orderBy('id')])
            ->get();

        foreach ($connections as $connection) {
            foreach ($connection->repositories as $repository) {
                if (!$this->shouldSync($connection, $repository)) {
                    $skipped++;

                    continue;
                }

                SyncProductRepositoryJob::dispatch($repository->id);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} VCS sync job(s); skipped {$skipped} not due.");

        return self::SUCCESS;
    }

    private function shouldSync(OrganizationVcsConnection $connection, ProductRepository $repository): bool
    {
        return $connection->sync_schedule->isDue($repository->last_synced_at);
    }
}

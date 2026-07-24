<?php

namespace App\Services;

use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationSyncSchedule;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsSyncSchedule;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationVcsConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpsQueueHealthHintService
{
    public const STALE_JOB_MINUTES = 30;

    public const LEVEL_WARN = 'warn';

    public const LEVEL_FAIL = 'fail';

    public const CODE_QUEUE_SYNC = 'queue_sync';

    public const CODE_STALE_JOBS = 'stale_jobs';

    /**
     * Lightweight ops signal for Integration Health / Settings when scheduled
     * sync is enabled but queue/worker baseline looks unhealthy.
     *
     * @return array{level: 'warn'|'fail', code: string}|null
     */
    public function hintForOrganization(Organization $organization): ?array
    {
        if (!$this->organizationUsesScheduledSync($organization)) {
            return null;
        }

        $queue = (string) config('queue.default');

        if ($queue === 'sync') {
            return [
                'level' => self::LEVEL_FAIL,
                'code' => self::CODE_QUEUE_SYNC,
            ];
        }

        if ($queue === 'database' && $this->hasStalePendingJobs()) {
            return [
                'level' => self::LEVEL_FAIL,
                'code' => self::CODE_STALE_JOBS,
            ];
        }

        return null;
    }

    private function organizationUsesScheduledSync(Organization $organization): bool
    {
        $hasVcs = OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->where('status', VcsConnectionStatus::Active)
            ->whereIn('sync_schedule', [
                VcsSyncSchedule::Hourly->value,
                VcsSyncSchedule::Daily->value,
            ])
            ->exists();

        if ($hasVcs) {
            return true;
        }

        return OrganizationIntegration::query()
            ->where('organization_id', $organization->id)
            ->where('status', IntegrationConnectionStatus::Active)
            ->whereIn('sync_schedule', [
                IntegrationSyncSchedule::Hourly->value,
                IntegrationSyncSchedule::Daily->value,
            ])
            ->exists();
    }

    private function hasStalePendingJobs(): bool
    {
        try {
            if (!Schema::hasTable('jobs')) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        $threshold = now()->subMinutes(self::STALE_JOB_MINUTES)->getTimestamp();

        try {
            return DB::table('jobs')
                ->where('available_at', '<=', $threshold)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}

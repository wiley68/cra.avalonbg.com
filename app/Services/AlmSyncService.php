<?php

namespace App\Services;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Models\IntegrationSyncRun;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\Integrations\JiraCloudProvider;
use App\Support\AuditLogger;
use Throwable;

class AlmSyncService
{
    public function __construct(
        private readonly ImportSuggestionService $suggestions,
    ) {
    }

    public function sync(ProductIntegrationLink $link, ?User $actor = null): IntegrationSyncRun
    {
        $link->loadMissing(['integration', 'product']);

        $run = IntegrationSyncRun::query()->create([
            'link_id' => $link->id,
            'status' => IntegrationSyncRunStatus::Running,
            'triggered_by' => $actor?->id,
            'started_at' => now(),
        ]);

        try {
            if ($link->integration->provider !== IntegrationProvider::Jira) {
                throw new \RuntimeException('Only Jira ALM sync is implemented.');
            }

            $projectKey = trim((string) ($link->external_project_key ?? ''));
            if ($projectKey === '') {
                throw new \RuntimeException('Jira project key is missing on the product link.');
            }

            $provider = JiraCloudProvider::fromIntegration($link->integration);
            $issues = $provider->listIssues($projectKey, 50);
            $suggestionStats = $this->suggestions->upsertTaskSuggestionsFromAlm($link, $issues);

            $summary = [
                'provider' => IntegrationProvider::Jira->value,
                'project_key' => $projectKey,
                'issues_count' => count($issues),
                'synced_at' => now()->toIso8601String(),
                'sync_run_id' => $run->id,
                ...$suggestionStats,
            ];

            $link->update([
                'last_synced_at' => now(),
                'last_sync_summary' => $summary,
            ]);

            $run->update([
                'status' => IntegrationSyncRunStatus::Succeeded,
                'finished_at' => now(),
                'summary' => $summary,
            ]);

            AuditLogger::logIntegrationSyncSucceeded(
                $link->fresh(['product', 'integration']),
                $run->fresh(),
                $actor,
            );

            return $run->fresh();
        } catch (Throwable $exception) {
            $errorSummary = [
                'error' => $exception->getMessage(),
            ];

            $link->update([
                'last_synced_at' => now(),
                'last_sync_summary' => array_merge(
                    is_array($link->last_sync_summary) ? $link->last_sync_summary : [],
                    $errorSummary,
                ),
            ]);

            $run->update([
                'status' => IntegrationSyncRunStatus::Failed,
                'finished_at' => now(),
                'summary' => $errorSummary,
            ]);

            AuditLogger::logIntegrationSyncFailed(
                $link->fresh(['product', 'integration']),
                $run->fresh(),
                $actor,
                $exception->getMessage(),
            );

            return $run->fresh();
        }
    }
}

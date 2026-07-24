<?php

namespace App\Services;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Exceptions\IntegrationSoftFailException;
use App\Models\IntegrationSyncRun;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\Integrations\AzureDevOpsProvider;
use App\Services\Integrations\JiraCloudProvider;
use App\Support\AuditLogger;
use App\Support\Translations;
use Throwable;

class AlmSyncService
{
    public function __construct(
        private readonly ImportSuggestionService $suggestions,
        private readonly EvidenceService $evidence,
    ) {}

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
            $providerEnum = $link->integration->provider;

            if (! in_array($providerEnum, [IntegrationProvider::Jira, IntegrationProvider::AzureDevops], true)) {
                throw new \RuntimeException(
                    Translations::get('products.integrations.alm_sync_not_implemented'),
                );
            }

            $projectKey = trim((string) ($link->external_project_key ?? ''));
            if ($projectKey === '') {
                throw new \RuntimeException(
                    Translations::get(
                        $providerEnum === IntegrationProvider::AzureDevops
                        ? 'products.integrations.azure_devops_project_missing'
                        : 'products.integrations.jira_project_key_missing',
                    ),
                );
            }

            $provider = match ($providerEnum) {
                IntegrationProvider::AzureDevops => AzureDevOpsProvider::fromIntegration($link->integration),
                default => JiraCloudProvider::fromIntegration($link->integration),
            };

            $lastError = null;
            $issues = [];

            try {
                $issues = $provider->listIssues($projectKey, 50);
            } catch (IntegrationSoftFailException $softFail) {
                $lastError = $softFail->getMessage();
            }

            $suggestionStats = $this->suggestions->upsertTaskSuggestionsFromAlm($link, $issues);
            $providerValue = $providerEnum->value;

            $summary = [
                'provider' => $providerValue,
                'project_key' => $projectKey,
                'external_target_id' => $link->external_target_id,
                'issues_count' => count($issues),
                'issue_refs' => $this->issueRefs($issues),
                'synced_at' => now()->toIso8601String(),
                'sync_run_id' => $run->id,
                ...$suggestionStats,
            ];

            if ($lastError !== null) {
                $summary['last_error'] = $lastError;
                $summary['soft_fail'] = true;
            }

            $snapshot = $this->evidence->createIntegrationSnapshot(
                product: $link->product,
                snapshot: $summary,
                title: ucfirst(str_replace('_', ' ', $providerValue)).' sync — '.$projectKey.' — '.now()->format('Y-m-d H:i'),
                source: $providerValue.':'.$projectKey,
                uploader: $actor,
                notes: 'Auto-created from integration sync run #'.$run->id,
                filenamePrefix: $providerValue.'-sync',
            );

            $summary['evidence_id'] = $snapshot->id;
            $summary['evidence_checksum_sha256'] = $snapshot->checksum_sha256;

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
            $message = $exception->getMessage();
            $errorSummary = [
                'error' => $message,
                'last_error' => $message,
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
                $message,
            );

            return $run->fresh();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array{external_id: string, key: string, html_url: string|null, updated_at: string|null}>
     */
    private function issueRefs(array $issues): array
    {
        $refs = [];

        foreach (array_slice($issues, 0, 50) as $issue) {
            $externalId = trim((string) ($issue['external_id'] ?? ''));
            $key = trim((string) ($issue['key'] ?? ''));

            if ($externalId === '' || $key === '') {
                continue;
            }

            $refs[] = [
                'external_id' => $externalId,
                'key' => $key,
                'html_url' => isset($issue['html_url']) && is_string($issue['html_url'])
                    ? $issue['html_url']
                    : null,
                'updated_at' => isset($issue['updated_at']) && is_string($issue['updated_at'])
                    ? $issue['updated_at']
                    : null,
            ];
        }

        return $refs;
    }
}

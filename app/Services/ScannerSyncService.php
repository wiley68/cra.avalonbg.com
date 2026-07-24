<?php

namespace App\Services;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Models\IntegrationSyncRun;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\Integrations\SnykApiProvider;
use App\Support\AuditLogger;
use Throwable;

class ScannerSyncService
{
    public function __construct(
        private readonly ImportSuggestionService $suggestions,
        private readonly EvidenceService $evidence,
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
            if ($link->integration->provider !== IntegrationProvider::Snyk) {
                throw new \RuntimeException('Only Snyk scanner sync is implemented.');
            }

            $config = is_array($link->config) ? $link->config : [];
            $orgId = trim((string) ($config['org_id'] ?? $link->external_project_key ?? ''));
            $projectId = trim((string) ($link->external_target_id ?? ''));

            if ($orgId === '' || $projectId === '') {
                throw new \RuntimeException('Snyk org or project id is missing on the product link.');
            }

            $provider = SnykApiProvider::fromIntegration($link->integration);
            $findings = $provider->listFindings($orgId, $projectId, 50);
            $suggestionStats = $this->suggestions->upsertVulnerabilitySuggestionsFromScanner($link, $findings);

            $summary = [
                'provider' => IntegrationProvider::Snyk->value,
                'org_id' => $orgId,
                'project_id' => $projectId,
                'findings_count' => count($findings),
                'finding_refs' => $this->findingRefs($findings),
                'synced_at' => now()->toIso8601String(),
                'sync_run_id' => $run->id,
                ...$suggestionStats,
            ];

            $snapshot = $this->evidence->createIntegrationSnapshot(
                product: $link->product,
                snapshot: $summary,
                title: 'Snyk sync — ' . ($link->external_label ?: $projectId) . ' — ' . now()->format('Y-m-d H:i'),
                source: 'snyk:' . $orgId . '/' . $projectId,
                uploader: $actor,
                notes: 'Auto-created from integration sync run #' . $run->id,
                filenamePrefix: 'snyk-sync',
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

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array{
     *     external_id: string,
     *     cve_id: string|null,
     *     snyk_issue_key: string|null,
     *     html_url: string|null,
     *     severity: string|null
     * }>
     */
    private function findingRefs(array $findings): array
    {
        $refs = [];

        foreach (array_slice($findings, 0, 50) as $finding) {
            $externalId = trim((string) ($finding['external_id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $refs[] = [
                'external_id' => $externalId,
                'cve_id' => isset($finding['cve_id']) && is_string($finding['cve_id'])
                    ? $finding['cve_id']
                    : null,
                'snyk_issue_key' => isset($finding['snyk_issue_key']) && is_string($finding['snyk_issue_key'])
                    ? $finding['snyk_issue_key']
                    : null,
                'html_url' => isset($finding['html_url']) && is_string($finding['html_url'])
                    ? $finding['html_url']
                    : null,
                'severity' => isset($finding['severity']) && is_string($finding['severity'])
                    ? $finding['severity']
                    : null,
            ];
        }

        return $refs;
    }
}

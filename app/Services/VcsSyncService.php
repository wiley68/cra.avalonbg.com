<?php

namespace App\Services;

use App\Contracts\VcsProvider;
use App\Enums\VcsAuthType;
use App\Enums\VcsProvider as VcsProviderEnum;
use App\Enums\VcsSyncRunStatus;
use App\Models\OrganizationVcsConnection;
use App\Models\ProductRepository;
use App\Models\User;
use App\Models\VcsSyncRun;
use App\Services\Vcs\GitHubAppTokenService;
use App\Services\Vcs\GitHubPatProvider;
use App\Services\Vcs\GitLabPatProvider;
use App\Support\AuditLogger;
use RuntimeException;
use Throwable;

class VcsSyncService
{
    public function __construct(
        private readonly EvidenceService $evidence,
        private readonly VcsImportSuggestionService $suggestions,
        private readonly GitHubAppTokenService $githubAppTokens,
    ) {
    }

    public function sync(ProductRepository $repository, ?User $actor = null): VcsSyncRun
    {
        $repository->loadMissing(['connection', 'product']);

        $run = VcsSyncRun::query()->create([
            'repository_id' => $repository->id,
            'status' => VcsSyncRunStatus::Running,
            'triggered_by' => $actor?->id,
            'started_at' => now(),
        ]);

        try {
            $provider = $this->providerFor($repository);
            $branch = $repository->default_branch ?: 'main';
            $fullName = $repository->full_name;

            $tags = $provider->listTags($fullName);
            $releases = $provider->listReleases($fullName);
            $ci = $provider->defaultBranchCiStatus($fullName, $branch);
            $alerts = $provider->listDependencyAlerts($fullName);
            $suggestionStats = $this->suggestions->upsertFromSync($repository, $releases, $alerts);

            $summary = [
                'full_name' => $fullName,
                'default_branch' => $branch,
                'tags_count' => count($tags),
                'releases_count' => count($releases),
                'alerts_count' => count($alerts),
                'latest_tag' => $tags[0]['name'] ?? null,
                'latest_release' => $releases[0]['tag_name'] ?? null,
                'ci' => $ci,
                'tags' => array_slice($tags, 0, 10),
                'releases' => array_slice($releases, 0, 10),
                'synced_at' => now()->toIso8601String(),
                'sync_run_id' => $run->id,
                ...$suggestionStats,
            ];

            $snapshot = $this->evidence->createIntegrationSnapshot(
                product: $repository->product,
                snapshot: $summary,
                title: 'VCS sync — ' . $fullName . ' — ' . now()->format('Y-m-d H:i'),
                source: $repository->connection->provider->value . ':' . $fullName,
                uploader: $actor,
                notes: 'Auto-created from VCS sync run #' . $run->id,
            );

            $summary['evidence_id'] = $snapshot->id;
            $summary['evidence_checksum_sha256'] = $snapshot->checksum_sha256;

            $repository->update([
                'last_synced_at' => now(),
                'last_sync_summary' => $summary,
            ]);

            $run->update([
                'status' => VcsSyncRunStatus::Succeeded,
                'finished_at' => now(),
                'summary' => $summary,
            ]);

            AuditLogger::logVcsSyncSucceeded($repository->fresh(['product']), $run->fresh(), $actor);

            return $run->fresh();
        } catch (Throwable $exception) {
            $errorSummary = [
                'error' => $exception->getMessage(),
            ];

            $repository->update([
                'last_synced_at' => now(),
                'last_sync_summary' => array_merge(
                    is_array($repository->last_sync_summary) ? $repository->last_sync_summary : [],
                    $errorSummary,
                ),
            ]);

            $run->update([
                'status' => VcsSyncRunStatus::Failed,
                'finished_at' => now(),
                'summary' => $errorSummary,
            ]);

            AuditLogger::logVcsSyncFailed($repository->fresh(['product']), $run->fresh(), $actor, $exception->getMessage());

            return $run->fresh();
        }
    }

    private function providerFor(ProductRepository $repository): VcsProvider
    {
        $connection = $repository->connection;

        if ($connection === null) {
            throw new RuntimeException('Repository has no VCS connection.');
        }

        return match ($connection->provider) {
            VcsProviderEnum::Github => new GitHubPatProvider($this->githubAccessToken($connection)),
            VcsProviderEnum::Gitlab => new GitLabPatProvider((string) $connection->token),
            default => throw new RuntimeException('Unsupported VCS provider: ' . $connection->provider->value),
        };
    }

    private function githubAccessToken(OrganizationVcsConnection $connection): string
    {
        return match ($connection->auth_type) {
            VcsAuthType::GithubApp => $this->githubAppTokens->installationAccessToken($connection),
            VcsAuthType::Pat => (string) $connection->token,
        };
    }
}

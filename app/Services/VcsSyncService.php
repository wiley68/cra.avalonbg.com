<?php

namespace App\Services;

use App\Contracts\VcsProvider;
use App\Enums\VcsProvider as VcsProviderEnum;
use App\Enums\VcsSyncRunStatus;
use App\Models\ProductRepository;
use App\Models\User;
use App\Models\VcsSyncRun;
use App\Services\Vcs\GitHubPatProvider;
use App\Support\AuditLogger;
use RuntimeException;
use Throwable;

class VcsSyncService
{
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

            $summary = [
                'full_name' => $fullName,
                'default_branch' => $branch,
                'tags_count' => count($tags),
                'releases_count' => count($releases),
                'latest_tag' => $tags[0]['name'] ?? null,
                'latest_release' => $releases[0]['tag_name'] ?? null,
                'ci' => $ci,
                'tags' => array_slice($tags, 0, 10),
                'releases' => array_slice($releases, 0, 10),
            ];

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
            VcsProviderEnum::Github => new GitHubPatProvider($connection->token),
            default => throw new RuntimeException('Unsupported VCS provider: '.$connection->provider->value),
        };
    }
}

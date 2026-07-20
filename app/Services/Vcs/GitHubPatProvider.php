<?php

namespace App\Services\Vcs;

use App\Contracts\VcsProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubPatProvider implements VcsProvider
{
    public function __construct(
        private readonly string $token,
    ) {
    }

    public function listTags(string $fullName): array
    {
        $response = $this->client()->get("https://api.github.com/repos/{$fullName}/tags", [
            'per_page' => 30,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitHub tags (HTTP ' . $response->status() . ').');
        }

        $items = $response->json() ?? [];

        if (!is_array($items)) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item) || blank($item['name'] ?? null)) {
                continue;
            }

            $mapped[] = [
                'name' => (string) $item['name'],
                'commit_sha' => is_array($item['commit'] ?? null)
                    ? ($item['commit']['sha'] ?? null)
                    : null,
            ];
        }

        return $mapped;
    }

    public function listReleases(string $fullName): array
    {
        $response = $this->client()->get("https://api.github.com/repos/{$fullName}/releases", [
            'per_page' => 30,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitHub releases (HTTP ' . $response->status() . ').');
        }

        $items = $response->json() ?? [];

        if (!is_array($items)) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item) || blank($item['tag_name'] ?? null)) {
                continue;
            }

            $mapped[] = [
                'tag_name' => (string) $item['tag_name'],
                'name' => $item['name'] ?? null,
                'published_at' => $item['published_at'] ?? null,
                'html_url' => $item['html_url'] ?? null,
            ];
        }

        return $mapped;
    }

    public function defaultBranchCiStatus(string $fullName, string $defaultBranch): array
    {
        $response = $this->client()->get("https://api.github.com/repos/{$fullName}/actions/runs", [
            'branch' => $defaultBranch,
            'per_page' => 1,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch GitHub Actions status (HTTP ' . $response->status() . ').');
        }

        $payload = $response->json() ?? [];
        $runs = is_array($payload) ? ($payload['workflow_runs'] ?? []) : [];
        $run = is_array($runs) ? ($runs[0] ?? null) : null;

        if (!is_array($run)) {
            return [
                'status' => 'unknown',
                'conclusion' => null,
                'workflow_name' => null,
                'html_url' => null,
                'head_sha' => null,
            ];
        }

        return [
            'status' => (string) ($run['status'] ?? 'unknown'),
            'conclusion' => $run['conclusion'] ?? null,
            'workflow_name' => $run['name'] ?? null,
            'html_url' => $run['html_url'] ?? null,
            'head_sha' => $run['head_sha'] ?? null,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRA-Compliance-Workspace',
            ]);
    }
}

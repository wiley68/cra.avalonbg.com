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
                'body' => isset($item['body']) && is_string($item['body']) ? $item['body'] : null,
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

    public function listDependencyAlerts(string $fullName): array
    {
        $response = $this->client()->get("https://api.github.com/repos/{$fullName}/dependabot/alerts", [
            'state' => 'open',
            'per_page' => 30,
        ]);

        if (in_array($response->status(), [401, 403, 404], true)) {
            return [];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitHub Dependabot alerts (HTTP ' . $response->status() . ').');
        }

        $items = $response->json() ?? [];

        if (!is_array($items)) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $number = isset($item['number']) ? (int) $item['number'] : null;
            $advisory = is_array($item['security_advisory'] ?? null) ? $item['security_advisory'] : [];
            $dependency = is_array($item['dependency'] ?? null) ? $item['dependency'] : [];
            $package = is_array($dependency['package'] ?? null) ? $dependency['package'] : [];
            $ghsaId = isset($advisory['ghsa_id']) && is_string($advisory['ghsa_id'])
                ? $advisory['ghsa_id']
                : null;
            $externalId = $number !== null
                ? 'dependabot:' . $number
                : ($ghsaId ?? null);

            if ($externalId === null || $externalId === '') {
                continue;
            }

            $summary = isset($advisory['summary']) && is_string($advisory['summary']) && $advisory['summary'] !== ''
                ? $advisory['summary']
                : ('Dependabot alert' . ($number !== null ? ' #' . $number : ''));

            $severity = null;
            if (isset($advisory['severity']) && is_string($advisory['severity'])) {
                $severity = $advisory['severity'];
            } elseif (
                is_array($item['security_vulnerability'] ?? null)
                && isset($item['security_vulnerability']['severity'])
                && is_string($item['security_vulnerability']['severity'])
            ) {
                $severity = $item['security_vulnerability']['severity'];
            }

            $mapped[] = [
                'external_id' => $externalId,
                'number' => $number,
                'ghsa_id' => $ghsaId,
                'cve_id' => isset($advisory['cve_id']) && is_string($advisory['cve_id'])
                    ? $advisory['cve_id']
                    : null,
                'summary' => $summary,
                'severity' => $severity,
                'package_name' => isset($package['name']) && is_string($package['name'])
                    ? $package['name']
                    : null,
                'package_ecosystem' => isset($package['ecosystem']) && is_string($package['ecosystem'])
                    ? $package['ecosystem']
                    : null,
                'html_url' => isset($item['html_url']) && is_string($item['html_url'])
                    ? $item['html_url']
                    : null,
                'created_at' => isset($item['created_at']) && is_string($item['created_at'])
                    ? $item['created_at']
                    : null,
            ];
        }

        return $mapped;
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

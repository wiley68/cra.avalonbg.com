<?php

namespace App\Services\Vcs;

use App\Contracts\VcsProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabPatProvider implements VcsProvider
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://gitlab.com/api/v4',
    ) {
    }

    public function listTags(string $fullName): array
    {
        $response = $this->client()->get($this->projectUrl($fullName) . '/repository/tags', [
            'per_page' => 30,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitLab tags (HTTP ' . $response->status() . ').');
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
                    ? ($item['commit']['id'] ?? null)
                    : null,
            ];
        }

        return $mapped;
    }

    public function listReleases(string $fullName): array
    {
        $response = $this->client()->get($this->projectUrl($fullName) . '/releases', [
            'per_page' => 30,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitLab releases (HTTP ' . $response->status() . ').');
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

            $links = is_array($item['_links'] ?? null) ? $item['_links'] : [];

            $mapped[] = [
                'tag_name' => (string) $item['tag_name'],
                'name' => $item['name'] ?? null,
                'body' => isset($item['description']) && is_string($item['description'])
                    ? $item['description']
                    : null,
                'published_at' => $item['released_at'] ?? ($item['created_at'] ?? null),
                'html_url' => isset($links['self']) && is_string($links['self'])
                    ? $links['self']
                    : null,
            ];
        }

        return $mapped;
    }

    public function defaultBranchCiStatus(string $fullName, string $defaultBranch): array
    {
        $response = $this->client()->get($this->projectUrl($fullName) . '/pipelines', [
            'ref' => $defaultBranch,
            'per_page' => 1,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch GitLab pipeline status (HTTP ' . $response->status() . ').');
        }

        $items = $response->json() ?? [];
        $pipeline = is_array($items) ? ($items[0] ?? null) : null;

        if (!is_array($pipeline)) {
            return [
                'status' => 'unknown',
                'conclusion' => null,
                'workflow_name' => null,
                'html_url' => null,
                'head_sha' => null,
            ];
        }

        $gitlabStatus = (string) ($pipeline['status'] ?? 'unknown');

        return [
            'status' => $this->mapPipelineStatus($gitlabStatus),
            'conclusion' => $this->mapPipelineConclusion($gitlabStatus),
            'workflow_name' => isset($pipeline['source']) && is_string($pipeline['source'])
                ? $pipeline['source']
                : 'pipeline',
            'html_url' => isset($pipeline['web_url']) && is_string($pipeline['web_url'])
                ? $pipeline['web_url']
                : null,
            'head_sha' => isset($pipeline['sha']) && is_string($pipeline['sha'])
                ? $pipeline['sha']
                : null,
        ];
    }

    public function listDependencyAlerts(string $fullName): array
    {
        $response = $this->client()->get($this->projectUrl($fullName) . '/vulnerability_findings', [
            'per_page' => 30,
        ]);

        if (in_array($response->status(), [401, 403, 404], true)) {
            return [];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitLab vulnerability findings (HTTP ' . $response->status() . ').');
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

            $uuid = isset($item['uuid']) && is_string($item['uuid']) ? $item['uuid'] : null;
            $id = isset($item['id']) ? (string) $item['id'] : null;
            $externalId = $uuid !== null && $uuid !== ''
                ? 'gitlab-vuln:' . $uuid
                : ($id !== null && $id !== '' ? 'gitlab-vuln:' . $id : null);

            if ($externalId === null) {
                continue;
            }

            $location = is_array($item['location'] ?? null) ? $item['location'] : [];
            $identifiers = is_array($item['identifiers'] ?? null) ? $item['identifiers'] : [];
            $cveId = null;
            $ghsaId = null;

            foreach ($identifiers as $identifier) {
                if (!is_array($identifier)) {
                    continue;
                }

                $type = strtolower((string) ($identifier['external_type'] ?? $identifier['type'] ?? ''));
                $value = isset($identifier['external_id']) && is_string($identifier['external_id'])
                    ? $identifier['external_id']
                    : (isset($identifier['name']) && is_string($identifier['name']) ? $identifier['name'] : null);

                if ($value === null) {
                    continue;
                }

                if ($type === 'cve' || str_starts_with(strtoupper($value), 'CVE-')) {
                    $cveId = $value;
                }

                if ($type === 'ghsa' || str_starts_with(strtoupper($value), 'GHSA-')) {
                    $ghsaId = $value;
                }
            }

            $summary = isset($item['name']) && is_string($item['name']) && $item['name'] !== ''
                ? $item['name']
                : (isset($item['description']) && is_string($item['description'])
                    ? $item['description']
                    : 'GitLab vulnerability finding');

            $mapped[] = [
                'external_id' => $externalId,
                'number' => isset($item['id']) ? (int) $item['id'] : null,
                'ghsa_id' => $ghsaId,
                'cve_id' => $cveId,
                'summary' => $summary,
                'severity' => isset($item['severity']) && is_string($item['severity'])
                    ? $item['severity']
                    : null,
                'package_name' => isset($location['dependency']['package']['name'])
                    && is_string($location['dependency']['package']['name'])
                    ? $location['dependency']['package']['name']
                    : null,
                'package_ecosystem' => null,
                'html_url' => null,
                'created_at' => isset($item['created_at']) && is_string($item['created_at'])
                    ? $item['created_at']
                    : null,
            ];
        }

        return $mapped;
    }

    private function projectUrl(string $fullName): string
    {
        return rtrim($this->baseUrl, '/') . '/projects/' . rawurlencode($fullName);
    }

    private function mapPipelineStatus(string $status): string
    {
        return match ($status) {
            'success', 'failed', 'canceled', 'cancelled', 'skipped' => 'completed',
            'running', 'pending', 'created', 'waiting_for_resource', 'preparing', 'scheduled' => 'in_progress',
            default => $status,
        };
    }

    private function mapPipelineConclusion(string $status): ?string
    {
        return match ($status) {
            'success' => 'success',
            'failed' => 'failure',
            'canceled', 'cancelled' => 'cancelled',
            'skipped' => 'skipped',
            default => null,
        };
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'PRIVATE-TOKEN' => $this->token,
            'User-Agent' => 'CRA-Compliance-Workspace',
        ])->acceptJson();
    }
}

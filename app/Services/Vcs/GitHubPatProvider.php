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

    public function listDependencyUpdatePulls(string $fullName): array
    {
        $response = $this->client()->get("https://api.github.com/repos/{$fullName}/pulls", [
            'state' => 'open',
            'per_page' => 30,
            'sort' => 'updated',
            'direction' => 'desc',
        ]);

        if (in_array($response->status(), [401, 403, 404], true)) {
            return [];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Failed to list GitHub pull requests (HTTP ' . $response->status() . ').');
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

            $number = isset($item['number']) ? (int) $item['number'] : 0;
            $htmlUrl = isset($item['html_url']) && is_string($item['html_url']) ? $item['html_url'] : null;
            $title = isset($item['title']) && is_string($item['title']) ? $item['title'] : '';

            if ($number < 1 || $htmlUrl === null || $htmlUrl === '') {
                continue;
            }

            $user = is_array($item['user'] ?? null) ? $item['user'] : [];
            $login = isset($user['login']) && is_string($user['login']) ? strtolower($user['login']) : '';
            $head = is_array($item['head'] ?? null) ? $item['head'] : [];
            $headRef = isset($head['ref']) && is_string($head['ref']) ? $head['ref'] : null;

            $botSource = $this->dependencyBotSource($login, $headRef);

            if ($botSource === null) {
                continue;
            }

            $mapped[] = [
                'number' => $number,
                'title' => $title !== '' ? $title : ('PR #' . $number),
                'html_url' => $htmlUrl,
                'head_ref' => $headRef,
                'body' => isset($item['body']) && is_string($item['body']) ? $item['body'] : null,
                'bot_source' => $botSource,
                'package_hint' => $this->packageHintFromRef($headRef, $botSource),
            ];
        }

        return $mapped;
    }

    /**
     * Merged pull requests in an inclusive merged date window (YYYY-MM-DD).
     * Uses GitHub Search Issues API. Returns [] on 403/404 (insufficient scope).
     *
     * @return list<array{
     *     number: int,
     *     title: string,
     *     html_url: string,
     *     merged_at: string|null,
     *     user_login: string|null
     * }>
     */
    public function listMergedPulls(
        string $fullName,
        string $fromDate,
        string $toDate,
        int $perPage = 30,
    ): array {
        $query = sprintf(
            'repo:%s is:pr is:merged merged:%s..%s',
            $fullName,
            $fromDate,
            $toDate,
        );

        $response = $this->client()->get('https://api.github.com/search/issues', [
            'q' => $query,
            'per_page' => max(1, min(100, $perPage)),
            'sort' => 'updated',
            'order' => 'desc',
        ]);

        if (in_array($response->status(), [401, 403, 404, 422], true)) {
            return [];
        }

        if (!$response->successful()) {
            throw new RuntimeException('Failed to search GitHub merged pull requests (HTTP ' . $response->status() . ').');
        }

        $payload = $response->json() ?? [];
        $items = is_array($payload) ? ($payload['items'] ?? []) : [];

        if (!is_array($items)) {
            return [];
        }

        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $number = isset($item['number']) ? (int) $item['number'] : 0;
            $htmlUrl = isset($item['html_url']) && is_string($item['html_url']) ? $item['html_url'] : null;
            $title = isset($item['title']) && is_string($item['title']) ? $item['title'] : '';

            if ($number < 1 || $htmlUrl === null || $htmlUrl === '') {
                continue;
            }

            $user = is_array($item['user'] ?? null) ? $item['user'] : [];
            $login = isset($user['login']) && is_string($user['login']) ? $user['login'] : null;
            $mergedAt = isset($item['closed_at']) && is_string($item['closed_at'])
                ? $item['closed_at']
                : (isset($item['pull_request']['merged_at']) && is_string($item['pull_request']['merged_at'])
                    ? $item['pull_request']['merged_at']
                    : null);

            $mapped[] = [
                'number' => $number,
                'title' => $title !== '' ? $title : ('PR #' . $number),
                'html_url' => $htmlUrl,
                'merged_at' => $mergedAt,
                'user_login' => $login,
            ];
        }

        return $mapped;
    }

    /**
     * @return 'dependabot'|'renovate'|null
     */
    private function dependencyBotSource(string $login, ?string $headRef): ?string
    {
        if (in_array($login, ['dependabot[bot]', 'dependabot'], true)) {
            return 'dependabot';
        }

        if (in_array($login, ['renovate[bot]', 'renovate'], true)) {
            return 'renovate';
        }

        $ref = strtolower((string) $headRef);

        if (str_starts_with($ref, 'dependabot/')) {
            return 'dependabot';
        }

        if (str_starts_with($ref, 'renovate/')) {
            return 'renovate';
        }

        return null;
    }

    private function packageHintFromRef(?string $headRef, string $botSource): ?string
    {
        if ($headRef === null || $headRef === '') {
            return null;
        }

        $ref = strtolower($headRef);

        if ($botSource === 'dependabot' && str_starts_with($ref, 'dependabot/')) {
            $parts = explode('/', $ref);

            if (count($parts) < 3) {
                return null;
            }

            $last = $parts[count($parts) - 1];
            $prev = $parts[count($parts) - 2];

            if (preg_match('/^\d/', $last) === 1) {
                return $prev !== '' ? $prev : null;
            }

            $stripped = preg_replace('/-\d[\w.\-]*$/', '', $last);

            return is_string($stripped) && $stripped !== '' ? $stripped : $last;
        }

        if ($botSource === 'renovate' && str_starts_with($ref, 'renovate/')) {
            $slug = substr($ref, strlen('renovate/'));
            $slug = preg_replace(
                '/^(npm|pip|composer|nuget|maven|go|docker|github-tags|github-releases)-/',
                '',
                $slug,
            );
            $slug = is_string($slug) ? $slug : '';
            $stripped = preg_replace('/-\d.*$/', '', $slug);

            return is_string($stripped) && $stripped !== '' ? $stripped : ($slug !== '' ? $slug : null);
        }

        return null;
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

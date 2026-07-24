<?php

namespace App\Services\Integrations;

use App\Contracts\ScannerProvider;
use App\Enums\IntegrationProvider;
use App\Models\OrganizationIntegration;
use App\Support\Translations;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SnykApiProvider implements ScannerProvider
{
    public const DEFAULT_BASE_URL = 'https://api.snyk.io';

    public const API_VERSION = '2024-10-15';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiToken,
    ) {
    }

    public static function fromIntegration(OrganizationIntegration $integration): self
    {
        if ($integration->provider !== IntegrationProvider::Snyk) {
            throw new RuntimeException(
                Translations::get('products.integrations.snyk_provider_mismatch'),
            );
        }

        $credentials = is_array($integration->credentials) ? $integration->credentials : [];
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? self::DEFAULT_BASE_URL), '/');
        $apiToken = (string) ($credentials['api_token'] ?? '');

        if ($baseUrl === '' || $apiToken === '') {
            throw ValidationException::withMessages([
                'integration' => [Translations::get('products.integrations.snyk_credentials_missing')],
            ]);
        }

        return new self($baseUrl, $apiToken);
    }

    public function getProject(string $orgId, string $projectId): array
    {
        $org = trim($orgId);
        $project = trim($projectId);

        $response = $this->client()->get(
            $this->baseUrl . '/rest/orgs/' . rawurlencode($org) . '/projects/' . rawurlencode($project),
            ['version' => self::API_VERSION],
        );

        if ($response->status() === 404) {
            throw ValidationException::withMessages([
                'project_id' => [Translations::get('products.integrations.snyk_project_not_found')],
            ]);
        }

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'project_id' => [Translations::get('products.integrations.snyk_project_fetch_failed')],
            ]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

        return [
            'id' => (string) ($data['id'] ?? $project),
            'name' => (string) ($attributes['name'] ?? $project),
            'org_id' => $org,
        ];
    }

    public function listFindings(string $orgId, string $projectId, int $limit = 50): array
    {
        $org = trim($orgId);
        $project = trim($projectId);

        $response = $this->client()->get(
            $this->baseUrl . '/rest/orgs/' . rawurlencode($org) . '/issues',
            [
                'version' => self::API_VERSION,
                'scan_item.id' => $project,
                'scan_item.type' => 'project',
                'status' => 'open',
                'limit' => max(1, min($limit, 100)),
            ],
        );

        if (!$response->successful()) {
            throw new RuntimeException(
                Translations::get('products.integrations.snyk_findings_fetch_failed'),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $items = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $mapped = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $externalId = trim((string) ($item['id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $title = trim((string) ($attributes['title'] ?? $attributes['key'] ?? $externalId));
            $issueKey = isset($attributes['key']) && is_string($attributes['key'])
                ? $attributes['key']
                : null;
            $severity = $this->severityFromAttributes($attributes);
            $cveId = $this->cveFromAttributes($attributes);
            $package = $this->packageFromAttributes($attributes);
            $createdAt = isset($attributes['created_at']) && is_string($attributes['created_at'])
                ? $attributes['created_at']
                : null;
            $htmlUrl = $this->issueUrl($org, $project, $externalId);

            $summaryParts = [];
            if ($package['name'] !== null) {
                $summaryParts[] = 'Package: ' . $package['name'];
            }
            if ($issueKey !== null) {
                $summaryParts[] = 'Key: ' . $issueKey;
            }

            $mapped[] = [
                'external_id' => $externalId,
                'title' => $title !== '' ? $title : $externalId,
                'summary' => $summaryParts !== [] ? implode(' · ', $summaryParts) : null,
                'cve_id' => $cveId,
                'severity' => $severity,
                'package_name' => $package['name'],
                'package_ecosystem' => $package['ecosystem'],
                'html_url' => $htmlUrl,
                'snyk_issue_key' => $issueKey,
                'created_at' => $createdAt,
                'cvss_score' => $this->cvssFromAttributes($attributes),
            ];
        }

        return $mapped;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'token ' . $this->apiToken,
            'Content-Type' => 'application/vnd.api+json',
            'Accept' => 'application/vnd.api+json',
            'User-Agent' => 'CRA-Compliance-Workspace',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function severityFromAttributes(array $attributes): ?string
    {
        $level = $attributes['effective_severity_level']
            ?? $attributes['severity']
            ?? null;

        if (!is_string($level) || $level === '') {
            return null;
        }

        return strtolower($level);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function cveFromAttributes(array $attributes): ?string
    {
        $problems = $attributes['problems'] ?? null;
        if (!is_array($problems)) {
            return null;
        }

        foreach ($problems as $problem) {
            if (!is_array($problem)) {
                continue;
            }

            $id = $problem['id'] ?? null;
            if (is_string($id) && str_starts_with(strtoupper($id), 'CVE-')) {
                return strtoupper($id);
            }

            $source = $problem['source'] ?? null;
            if ($source === 'CVE' && isset($problem['id']) && is_string($problem['id'])) {
                return strtoupper($problem['id']);
            }
        }

        $identifiers = $attributes['identifiers'] ?? null;
        if (is_array($identifiers)) {
            $cves = $identifiers['CVE'] ?? $identifiers['cve'] ?? null;
            if (is_array($cves) && isset($cves[0]) && is_string($cves[0])) {
                return strtoupper($cves[0]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{name: string|null, ecosystem: string|null}
     */
    private function packageFromAttributes(array $attributes): array
    {
        $coordinates = $attributes['coordinates'] ?? null;
        if (is_array($coordinates) && isset($coordinates[0]) && is_array($coordinates[0])) {
            $repr = $coordinates[0]['representations'] ?? null;
            if (is_string($repr) && $repr !== '') {
                return [
                    'name' => $repr,
                    'ecosystem' => isset($coordinates[0]['ecosystem']) && is_string($coordinates[0]['ecosystem'])
                        ? $coordinates[0]['ecosystem']
                        : null,
                ];
            }
        }

        $package = $attributes['package'] ?? null;
        if (is_array($package)) {
            $name = isset($package['name']) && is_string($package['name']) ? $package['name'] : null;
            $ecosystem = isset($package['ecosystem']) && is_string($package['ecosystem'])
                ? $package['ecosystem']
                : null;

            return ['name' => $name, 'ecosystem' => $ecosystem];
        }

        return ['name' => null, 'ecosystem' => null];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function cvssFromAttributes(array $attributes): ?string
    {
        $score = $attributes['cvss_score']
            ?? $attributes['effective_severity_score']
            ?? null;

        if (is_int($score) || is_float($score)) {
            return (string) $score;
        }

        if (is_string($score) && $score !== '') {
            return $score;
        }

        return null;
    }

    private function issueUrl(string $orgId, string $projectId, string $issueId): string
    {
        $appHost = str_replace('api.', 'app.', parse_url($this->baseUrl, PHP_URL_HOST) ?: 'app.snyk.io');

        return sprintf(
            'https://%s/org/%s/project/%s#issue-%s',
            $appHost,
            rawurlencode($orgId),
            rawurlencode($projectId),
            rawurlencode($issueId),
        );
    }
}

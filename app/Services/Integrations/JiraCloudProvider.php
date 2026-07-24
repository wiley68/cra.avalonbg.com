<?php

namespace App\Services\Integrations;

use App\Contracts\AlmProvider;
use App\Enums\IntegrationProvider;
use App\Models\OrganizationIntegration;
use App\Support\Translations;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class JiraCloudProvider implements AlmProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $email,
        private readonly string $apiToken,
    ) {
    }

    public static function fromIntegration(OrganizationIntegration $integration): self
    {
        if ($integration->provider !== IntegrationProvider::Jira) {
            throw new RuntimeException(
                Translations::get('products.integrations.jira_provider_mismatch'),
            );
        }

        $credentials = is_array($integration->credentials) ? $integration->credentials : [];
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? ''), '/');
        $email = trim((string) ($credentials['email'] ?? ''));
        $apiToken = (string) ($credentials['api_token'] ?? '');

        if ($baseUrl === '' || $email === '' || $apiToken === '') {
            throw ValidationException::withMessages([
                'integration' => [Translations::get('products.integrations.jira_credentials_missing')],
            ]);
        }

        return new self($baseUrl, $email, $apiToken);
    }

    public function getProject(string $projectKey): array
    {
        $key = strtoupper(trim($projectKey));

        $response = $this->client()->get($this->baseUrl . '/rest/api/3/project/' . rawurlencode($key));

        if ($response->status() === 404) {
            throw ValidationException::withMessages([
                'project_key' => [Translations::get('products.integrations.jira_project_not_found')],
            ]);
        }

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'project_key' => [Translations::get('products.integrations.jira_project_fetch_failed')],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return [
            'key' => (string) ($data['key'] ?? $key),
            'name' => (string) ($data['name'] ?? $key),
            'id' => isset($data['id']) ? (string) $data['id'] : null,
        ];
    }

    public function listIssues(string $projectKey, int $maxResults = 50): array
    {
        $key = strtoupper(trim($projectKey));
        $jql = sprintf('project = "%s" ORDER BY updated DESC', addslashes($key));

        // Legacy GET/POST /rest/api/3/search was removed on Jira Cloud (410 Gone).
        // Use the enhanced search endpoint instead.
        $response = $this->client()->post($this->baseUrl . '/rest/api/3/search/jql', [
            'jql' => $jql,
            'maxResults' => max(1, min($maxResults, 100)),
            'fields' => [
                'summary',
                'description',
                'issuetype',
                'priority',
                'status',
                'updated',
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                Translations::get('products.integrations.jira_issues_fetch_failed'),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $issues = is_array($payload['issues'] ?? null) ? $payload['issues'] : [];
        $mapped = [];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $issueKey = trim((string) ($issue['key'] ?? ''));
            $externalId = trim((string) ($issue['id'] ?? $issueKey));

            if ($issueKey === '' || $externalId === '') {
                continue;
            }

            $fields = is_array($issue['fields'] ?? null) ? $issue['fields'] : [];
            $summary = isset($fields['summary']) && is_string($fields['summary'])
                ? $fields['summary']
                : $issueKey;

            $mapped[] = [
                'external_id' => $externalId,
                'key' => $issueKey,
                'title' => $summary,
                'summary' => $this->plainDescription($fields['description'] ?? null),
                'issue_type' => is_array($fields['issuetype'] ?? null)
                    ? (string) ($fields['issuetype']['name'] ?? '')
                    : null,
                'priority' => is_array($fields['priority'] ?? null)
                    ? (string) ($fields['priority']['name'] ?? '')
                    : null,
                'status' => is_array($fields['status'] ?? null)
                    ? (string) ($fields['status']['name'] ?? '')
                    : null,
                'html_url' => $this->baseUrl . '/browse/' . $issueKey,
                'updated_at' => isset($fields['updated']) && is_string($fields['updated'])
                    ? $fields['updated']
                    : null,
            ];
        }

        return $mapped;
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->email, $this->apiToken)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'CRA-Compliance-Workspace',
            ]);
    }

    private function plainDescription(mixed $description): ?string
    {
        if (is_string($description) && $description !== '') {
            return mb_substr($description, 0, 2000);
        }

        if (!is_array($description)) {
            return null;
        }

        $chunks = [];
        $this->collectAdfText($description, $chunks);
        $text = trim(implode(' ', $chunks));

        return $text !== '' ? mb_substr($text, 0, 2000) : null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $chunks
     */
    private function collectAdfText(array $node, array &$chunks): void
    {
        if (isset($node['text']) && is_string($node['text']) && $node['text'] !== '') {
            $chunks[] = $node['text'];
        }

        $content = $node['content'] ?? null;
        if (!is_array($content)) {
            return;
        }

        foreach ($content as $child) {
            if (is_array($child)) {
                $this->collectAdfText($child, $chunks);
            }
        }
    }
}

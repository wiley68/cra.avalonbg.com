<?php

namespace App\Services\Integrations;

use App\Contracts\AlmProvider;
use App\Enums\IntegrationProvider;
use App\Exceptions\IntegrationSoftFailException;
use App\Models\OrganizationIntegration;
use App\Support\Translations;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AzureDevOpsProvider implements AlmProvider
{
    public const DEFAULT_BASE_URL = 'https://dev.azure.com';

    public const API_VERSION = '7.1';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $organization,
        private readonly string $pat,
    ) {}

    public static function fromIntegration(OrganizationIntegration $integration): self
    {
        if ($integration->provider !== IntegrationProvider::AzureDevops) {
            throw new RuntimeException(
                Translations::get('products.integrations.azure_devops_provider_mismatch'),
            );
        }

        $credentials = is_array($integration->credentials) ? $integration->credentials : [];
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? self::DEFAULT_BASE_URL), '/');
        $organization = trim((string) ($credentials['organization'] ?? ''));
        $pat = (string) ($credentials['pat'] ?? $credentials['api_token'] ?? '');

        if ($baseUrl === '' || $organization === '' || $pat === '') {
            throw ValidationException::withMessages([
                'integration' => [Translations::get('products.integrations.azure_devops_credentials_missing')],
            ]);
        }

        return new self($baseUrl, $organization, $pat);
    }

    public function organization(): string
    {
        return $this->organization;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getProject(string $projectKey): array
    {
        $project = trim($projectKey);

        $response = $this->client()->get(
            $this->orgRoot().'/_apis/projects/'.rawurlencode($project),
            ['api-version' => self::API_VERSION],
        );

        if ($response->status() === 404) {
            throw ValidationException::withMessages([
                'project' => [Translations::get('products.integrations.azure_devops_project_not_found')],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'project' => [Translations::get('products.integrations.azure_devops_project_fetch_failed')],
            ]);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $name = trim((string) ($data['name'] ?? $project));
        $id = isset($data['id']) ? (string) $data['id'] : null;

        return [
            'key' => $name !== '' ? $name : $project,
            'name' => $name !== '' ? $name : $project,
            'id' => $id,
        ];
    }

    public function listIssues(string $projectKey, int $maxResults = 50): array
    {
        $project = trim($projectKey);
        $limit = max(1, min($maxResults, 100));

        $wiqlResponse = $this->client()->asJson()->post(
            $this->orgRoot().'/'.rawurlencode($project).'/_apis/wit/wiql?api-version='.self::API_VERSION,
            [
                'query' => sprintf(
                    "Select [System.Id] From WorkItems Where [System.TeamProject] = '%s' Order By [System.ChangedDate] Desc",
                    str_replace("'", "''", $project),
                ),
            ],
        );

        if (in_array($wiqlResponse->status(), [401, 403, 404, 429], true)) {
            throw new IntegrationSoftFailException(
                Translations::get('products.integrations.azure_devops_work_items_scope_denied'),
                $wiqlResponse->status(),
            );
        }

        if (! $wiqlResponse->successful()) {
            throw new RuntimeException(
                Translations::get('products.integrations.azure_devops_work_items_fetch_failed'),
            );
        }

        /** @var array<string, mixed> $wiqlPayload */
        $wiqlPayload = $wiqlResponse->json() ?? [];
        $workItems = is_array($wiqlPayload['workItems'] ?? null) ? $wiqlPayload['workItems'] : [];
        $ids = [];

        foreach ($workItems as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $ids[] = (int) $item['id'];
            if (count($ids) >= $limit) {
                break;
            }
        }

        if ($ids === []) {
            return [];
        }

        $detailsResponse = $this->client()->get(
            $this->orgRoot().'/_apis/wit/workitems',
            [
                'ids' => implode(',', $ids),
                'fields' => implode(',', [
                    'System.Id',
                    'System.Title',
                    'System.Description',
                    'System.WorkItemType',
                    'System.State',
                    'Microsoft.VSTS.Common.Priority',
                    'System.ChangedDate',
                ]),
                'api-version' => self::API_VERSION,
            ],
        );

        if (in_array($detailsResponse->status(), [401, 403, 404, 429], true)) {
            throw new IntegrationSoftFailException(
                Translations::get('products.integrations.azure_devops_work_items_scope_denied'),
                $detailsResponse->status(),
            );
        }

        if (! $detailsResponse->successful()) {
            throw new RuntimeException(
                Translations::get('products.integrations.azure_devops_work_items_fetch_failed'),
            );
        }

        /** @var array<string, mixed> $detailsPayload */
        $detailsPayload = $detailsResponse->json() ?? [];
        $values = is_array($detailsPayload['value'] ?? null) ? $detailsPayload['value'] : [];
        $mapped = [];

        foreach ($values as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (string) $item['id'] : '';
            if ($id === '') {
                continue;
            }

            $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
            $title = isset($fields['System.Title']) && is_string($fields['System.Title'])
                ? $fields['System.Title']
                : ('#'.$id);
            $priority = $fields['Microsoft.VSTS.Common.Priority'] ?? null;

            $mapped[] = [
                'external_id' => $id,
                'key' => $id,
                'title' => $title,
                'summary' => $this->plainDescription($fields['System.Description'] ?? null),
                'issue_type' => isset($fields['System.WorkItemType']) && is_string($fields['System.WorkItemType'])
                    ? $fields['System.WorkItemType']
                    : null,
                'priority' => is_int($priority) || is_string($priority)
                    ? (string) $priority
                    : null,
                'status' => isset($fields['System.State']) && is_string($fields['System.State'])
                    ? $fields['System.State']
                    : null,
                'html_url' => $this->workItemUrl($project, $id),
                'updated_at' => isset($fields['System.ChangedDate']) && is_string($fields['System.ChangedDate'])
                    ? $fields['System.ChangedDate']
                    : null,
            ];
        }

        return $mapped;
    }

    private function orgRoot(): string
    {
        return $this->baseUrl.'/'.rawurlencode($this->organization);
    }

    private function workItemUrl(string $project, string $id): string
    {
        return $this->orgRoot().'/'.rawurlencode($project).'/_workitems/edit/'.rawurlencode($id);
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth('', $this->pat)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'CRA-Compliance-Workspace',
            ]);
    }

    private function plainDescription(mixed $description): ?string
    {
        if (! is_string($description) || $description === '') {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5));

        return $text !== '' ? mb_substr($text, 0, 2000) : null;
    }
}

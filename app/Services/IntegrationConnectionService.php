<?php

namespace App\Services;

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncSchedule;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class IntegrationConnectionService
{
    /**
     * @param  array{base_url: string, email: string, api_token: string}  $credentials
     */
    public function storeJira(
        Organization $organization,
        User $actor,
        array $credentials,
        ?string $label = null,
    ): OrganizationIntegration {
        $baseUrl = $this->normalizeJiraBaseUrl($credentials['base_url']);
        $email = trim($credentials['email']);
        $apiToken = $credentials['api_token'];

        $this->verifyJiraApiToken($baseUrl, $email, $apiToken);

        return $this->upsert(
            organization: $organization,
            actor: $actor,
            provider: IntegrationProvider::Jira,
            credentials: [
                'base_url' => $baseUrl,
                'email' => $email,
                'api_token' => $apiToken,
            ],
            label: $label ?: 'Jira Cloud',
        );
    }

    /**
     * @param  array{api_token: string, base_url?: string|null}  $credentials
     */
    public function storeSnyk(
        Organization $organization,
        User $actor,
        array $credentials,
        ?string $label = null,
    ): OrganizationIntegration {
        $baseUrl = $this->normalizeSnykBaseUrl($credentials['base_url'] ?? null);
        $apiToken = $credentials['api_token'];

        $this->verifySnykApiToken($baseUrl, $apiToken);

        return $this->upsert(
            organization: $organization,
            actor: $actor,
            provider: IntegrationProvider::Snyk,
            credentials: [
                'base_url' => $baseUrl,
                'api_token' => $apiToken,
            ],
            label: $label ?: 'Snyk',
        );
    }

    /**
     * @param  array{organization: string, pat: string, base_url?: string|null}  $credentials
     */
    public function storeAzureDevOps(
        Organization $organization,
        User $actor,
        array $credentials,
        ?string $label = null,
    ): OrganizationIntegration {
        $baseUrl = $this->normalizeAzureDevOpsBaseUrl($credentials['base_url'] ?? null);
        $adoOrganization = trim($credentials['organization']);
        $pat = $credentials['pat'];

        $this->verifyAzureDevOpsPat($baseUrl, $adoOrganization, $pat);

        return $this->upsert(
            organization: $organization,
            actor: $actor,
            provider: IntegrationProvider::AzureDevops,
            credentials: [
                'base_url' => $baseUrl,
                'organization' => $adoOrganization,
                'pat' => $pat,
            ],
            label: $label ?: 'Azure DevOps',
        );
    }

    public function storeSarif(
        Organization $organization,
        User $actor,
        ?string $label = null,
    ): OrganizationIntegration {
        return $this->upsert(
            organization: $organization,
            actor: $actor,
            provider: IntegrationProvider::Sarif,
            credentials: [],
            label: $label ?: 'SARIF / Trivy',
            authType: IntegrationAuthType::None,
        );
    }

    public function updateSyncSchedule(
        OrganizationIntegration $integration,
        IntegrationSyncSchedule $schedule,
        User $actor,
    ): OrganizationIntegration {
        $integration->update([
            'sync_schedule' => $schedule,
        ]);

        $fresh = $integration->fresh();
        AuditLogger::logIntegrationUpdated($fresh, $actor);

        return $fresh;
    }

    public function delete(OrganizationIntegration $integration, User $actor): void
    {
        AuditLogger::logIntegrationDisconnected($integration, $actor);
        $integration->delete();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function upsert(
        Organization $organization,
        User $actor,
        IntegrationProvider $provider,
        array $credentials,
        string $label,
        IntegrationAuthType $authType = IntegrationAuthType::ApiToken,
    ): OrganizationIntegration {
        $existing = OrganizationIntegration::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->first();

        $attributes = [
            'category' => $provider->category(),
            'auth_type' => $authType,
            'credentials' => $credentials === [] ? null : $credentials,
            'label' => $label,
            'status' => IntegrationConnectionStatus::Active,
            'last_verified_at' => now(),
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $integration = $existing->fresh();
            AuditLogger::logIntegrationUpdated($integration, $actor);

            return $integration;
        }

        $integration = OrganizationIntegration::query()->create([
            'organization_id' => $organization->id,
            'provider' => $provider,
            'sync_schedule' => IntegrationSyncSchedule::Off,
            ...$attributes,
        ]);

        AuditLogger::logIntegrationConnected($integration, $actor);

        return $integration;
    }

    private function normalizeJiraBaseUrl(string $baseUrl): string
    {
        $normalized = rtrim(trim($baseUrl), '/');

        if (!str_starts_with($normalized, 'https://') && !str_starts_with($normalized, 'http://')) {
            $normalized = 'https://' . $normalized;
        }

        return $normalized;
    }

    private function verifyJiraApiToken(string $baseUrl, string $email, string $apiToken): void
    {
        $response = Http::withBasicAuth($email, $apiToken)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->get($baseUrl . '/rest/api/3/myself');

        if ($response->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'api_token' => [Translations::get('settings.integrations.jira_credentials_invalid')],
        ]);
    }

    private function normalizeSnykBaseUrl(?string $baseUrl): string
    {
        $normalized = rtrim(trim((string) $baseUrl), '/');

        if ($normalized === '') {
            return 'https://api.snyk.io';
        }

        if (!str_starts_with($normalized, 'https://') && !str_starts_with($normalized, 'http://')) {
            $normalized = 'https://' . $normalized;
        }

        return $normalized;
    }

    private function verifySnykApiToken(string $baseUrl, string $apiToken): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'token ' . $apiToken,
            'Content-Type' => 'application/vnd.api+json',
            'Accept' => 'application/vnd.api+json',
            'User-Agent' => 'CRA-Compliance-Workspace',
        ])->get($baseUrl . '/rest/self', [
                    'version' => '2024-10-15',
                ]);

        if ($response->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'api_token' => [Translations::get('settings.integrations.snyk_credentials_invalid')],
        ]);
    }

    private function normalizeAzureDevOpsBaseUrl(?string $baseUrl): string
    {
        $normalized = rtrim(trim((string) $baseUrl), '/');

        if ($normalized === '') {
            return 'https://dev.azure.com';
        }

        if (!str_starts_with($normalized, 'https://') && !str_starts_with($normalized, 'http://')) {
            $normalized = 'https://' . $normalized;
        }

        return $normalized;
    }

    private function verifyAzureDevOpsPat(string $baseUrl, string $organization, string $pat): void
    {
        $response = Http::withBasicAuth('', $pat)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->get($baseUrl . '/' . rawurlencode($organization) . '/_apis/projects', [
                'api-version' => '7.1',
                '$top' => 1,
            ]);

        if ($response->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'pat' => [Translations::get('settings.integrations.azure_devops_credentials_invalid')],
        ]);
    }
}

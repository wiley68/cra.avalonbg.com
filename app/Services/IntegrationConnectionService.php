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
    ): OrganizationIntegration {
        $existing = OrganizationIntegration::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->first();

        $attributes = [
            'category' => $provider->category(),
            'auth_type' => IntegrationAuthType::ApiToken,
            'credentials' => $credentials,
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
}

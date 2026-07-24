<?php

namespace App\Http\Controllers\Settings;

use App\Enums\IntegrationSyncSchedule;
use App\Enums\VcsSyncSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAzureDevOpsIntegrationRequest;
use App\Http\Requests\Settings\StoreGithubAppVcsConnectionRequest;
use App\Http\Requests\Settings\StoreGithubVcsConnectionRequest;
use App\Http\Requests\Settings\StoreGitlabVcsConnectionRequest;
use App\Http\Requests\Settings\StoreJiraIntegrationRequest;
use App\Http\Requests\Settings\StoreSarifIntegrationRequest;
use App\Http\Requests\Settings\StoreSnykIntegrationRequest;
use App\Http\Requests\Settings\UpdateIntegrationSyncScheduleRequest;
use App\Http\Requests\Settings\UpdateVcsConnectionSyncScheduleRequest;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationVcsConnection;
use App\Services\IntegrationConnectionService;
use App\Services\VcsConnectionService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly VcsConnectionService $connections,
        private readonly IntegrationConnectionService $integrations,
    ) {
    }

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        if (!$user->canManageProducts($organization) && !$user->canViewProducts($organization)) {
            abort(403);
        }

        $connections = OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->orderBy('provider')
            ->get()
            ->map(fn(OrganizationVcsConnection $connection): array => [
                'id' => $connection->id,
                'provider' => $connection->provider->value,
                'auth_type' => $connection->auth_type->value,
                'label' => $connection->label,
                'status' => $connection->status->value,
                'sync_schedule' => $connection->sync_schedule->value,
                'github_app_id' => $connection->github_app_id,
                'github_installation_id' => $connection->github_installation_id,
                'has_github_private_key' => filled($connection->github_private_key),
                'webhook_configured' => filled($connection->webhook_secret),
                'webhook_url' => route('api.webhooks.github', $connection),
                'last_verified_at' => $connection->last_verified_at?->toIso8601String(),
                'created_at' => $connection->created_at?->toIso8601String(),
            ]);

        $integrations = OrganizationIntegration::query()
            ->where('organization_id', $organization->id)
            ->orderBy('provider')
            ->get()
            ->map(fn(OrganizationIntegration $integration): array => [
                'id' => $integration->id,
                'provider' => $integration->provider->value,
                'category' => $integration->category->value,
                'auth_type' => $integration->auth_type->value,
                'label' => $integration->label,
                'status' => $integration->status->value,
                'sync_schedule' => $integration->sync_schedule->value,
                'base_url' => is_array($integration->credentials)
                    ? ($integration->credentials['base_url'] ?? null)
                    : null,
                'email' => is_array($integration->credentials)
                    ? ($integration->credentials['email'] ?? null)
                    : null,
                'organization' => is_array($integration->credentials)
                    ? ($integration->credentials['organization'] ?? null)
                    : null,
                'last_verified_at' => $integration->last_verified_at?->toIso8601String(),
                'created_at' => $integration->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/Integrations', [
            'connections' => $connections,
            'integrations' => $integrations,
            'canManage' => $user->canManageProducts($organization),
            'revealed_webhook_secret' => $request->session()->pull('revealed_webhook_secret'),
        ]);
    }

    public function storeGithub(StoreGithubVcsConnectionRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->connections->storeGithubPat(
            organization: $organization,
            actor: $request->user(),
            token: $request->string('token')->toString(),
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.github_connected'),
        ]);

        return back();
    }

    public function storeGithubApp(StoreGithubAppVcsConnectionRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->connections->storeGithubApp(
            organization: $organization,
            actor: $request->user(),
            appId: $request->string('github_app_id')->toString(),
            installationId: $request->string('github_installation_id')->toString(),
            privateKey: $request->filled('github_private_key')
            ? $request->string('github_private_key')->toString()
            : null,
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.github_connected'),
        ]);

        return back();
    }

    public function storeGitlab(StoreGitlabVcsConnectionRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->connections->storeGitlabPat(
            organization: $organization,
            actor: $request->user(),
            token: $request->string('token')->toString(),
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.gitlab_connected'),
        ]);

        return back();
    }

    public function storeJira(StoreJiraIntegrationRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->integrations->storeJira(
            organization: $organization,
            actor: $request->user(),
            credentials: [
                'base_url' => $request->string('base_url')->toString(),
                'email' => $request->string('email')->toString(),
                'api_token' => $request->string('api_token')->toString(),
            ],
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.jira_connected'),
        ]);

        return back();
    }

    public function storeSnyk(StoreSnykIntegrationRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->integrations->storeSnyk(
            organization: $organization,
            actor: $request->user(),
            credentials: [
                'api_token' => $request->string('api_token')->toString(),
                'base_url' => $request->input('base_url'),
            ],
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.snyk_connected'),
        ]);

        return back();
    }

    public function storeAzureDevOps(StoreAzureDevOpsIntegrationRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->integrations->storeAzureDevOps(
            organization: $organization,
            actor: $request->user(),
            credentials: [
                'organization' => $request->string('organization')->toString(),
                'pat' => $request->string('pat')->toString(),
                'base_url' => $request->input('base_url'),
            ],
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.azure_devops_connected'),
        ]);

        return back();
    }

    public function storeSarif(StoreSarifIntegrationRequest $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->integrations->storeSarif(
            organization: $organization,
            actor: $request->user(),
            label: $request->input('label'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.sarif_connected'),
        ]);

        return back();
    }

    public function updateSyncSchedule(
        UpdateVcsConnectionSyncScheduleRequest $request,
        OrganizationVcsConnection $connection,
    ): RedirectResponse {
        $this->connections->updateSyncSchedule(
            connection: $connection,
            schedule: $request->enum('sync_schedule', VcsSyncSchedule::class),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.sync_schedule_updated'),
        ]);

        return back();
    }

    public function updateIntegrationSyncSchedule(
        UpdateIntegrationSyncScheduleRequest $request,
        OrganizationIntegration $integration,
    ): RedirectResponse {
        $this->integrations->updateSyncSchedule(
            integration: $integration,
            schedule: $request->enum('sync_schedule', IntegrationSyncSchedule::class),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.sync_schedule_updated'),
        ]);

        return back();
    }

    public function rotateWebhookSecret(
        Request $request,
        OrganizationVcsConnection $connection,
    ): RedirectResponse {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null || $connection->organization_id !== $organization->id) {
            abort(404);
        }

        if (!$user->canManageProducts($organization)) {
            abort(403);
        }

        $plain = $this->connections->rotateWebhookSecret($connection, $user);

        $request->session()->flash('revealed_webhook_secret', $plain);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.webhook_secret_rotated'),
        ]);

        return back();
    }

    public function destroy(Request $request, OrganizationVcsConnection $connection): RedirectResponse
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null || $connection->organization_id !== $organization->id) {
            abort(404);
        }

        if (!$user->canManageProducts($organization)) {
            abort(403);
        }

        $this->connections->delete($connection, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.disconnected'),
        ]);

        return back();
    }

    public function destroyIntegration(
        Request $request,
        OrganizationIntegration $integration,
    ): RedirectResponse {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null || $integration->organization_id !== $organization->id) {
            abort(404);
        }

        if (!$user->canManageProducts($organization)) {
            abort(403);
        }

        $this->integrations->delete($integration, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.integrations.disconnected'),
        ]);

        return back();
    }
}

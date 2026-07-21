<?php

namespace App\Http\Controllers\Settings;

use App\Enums\VcsSyncSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreGithubVcsConnectionRequest;
use App\Http\Requests\Settings\StoreGitlabVcsConnectionRequest;
use App\Http\Requests\Settings\UpdateVcsConnectionSyncScheduleRequest;
use App\Models\OrganizationVcsConnection;
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
                'webhook_configured' => filled($connection->webhook_secret),
                'webhook_url' => route('api.webhooks.github', $connection),
                'last_verified_at' => $connection->last_verified_at?->toIso8601String(),
                'created_at' => $connection->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/Integrations', [
            'connections' => $connections,
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
}

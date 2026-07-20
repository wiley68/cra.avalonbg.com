<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreGithubVcsConnectionRequest;
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
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        if (! $user->canManageProducts($organization) && ! $user->canViewProducts($organization)) {
            abort(403);
        }

        $connections = OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->orderBy('provider')
            ->get()
            ->map(fn (OrganizationVcsConnection $connection): array => [
                'id' => $connection->id,
                'provider' => $connection->provider->value,
                'auth_type' => $connection->auth_type->value,
                'label' => $connection->label,
                'status' => $connection->status->value,
                'last_verified_at' => $connection->last_verified_at?->toIso8601String(),
                'created_at' => $connection->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/Integrations', [
            'connections' => $connections,
            'canManage' => $user->canManageProducts($organization),
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

    public function destroy(Request $request, OrganizationVcsConnection $connection): RedirectResponse
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null || $connection->organization_id !== $organization->id) {
            abort(404);
        }

        if (! $user->canManageProducts($organization)) {
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

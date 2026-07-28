<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpsertOrganizationSsoRequest;
use App\Models\Organization;
use App\Services\OrganizationSsoService;
use App\Services\SamlClient;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SsoController extends Controller
{
    public function __construct(
        private readonly OrganizationSsoService $sso,
        private readonly SamlClient $saml,
    ) {
    }

    public function edit(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $plan = $organization->resolvedSubscriptionPlan();

        return Inertia::render('settings/Sso', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'subscription_plan' => $plan->value,
                'can_use_sso' => $organization->canUseSso(),
            ],
            'connection' => $this->sso->connectionPayload($organization->ssoConnection),
            'spEndpoints' => [
                'sp_entity_id' => $this->saml->spEntityId(),
                'acs_url' => $this->saml->acsUrl(),
                'metadata_url' => route('auth.sso.metadata', absolute: true),
            ],
            'providers' => [
                ['value' => 'generic', 'label' => Translations::get('sso.providers.generic')],
                ['value' => 'entra', 'label' => Translations::get('sso.providers.entra')],
                ['value' => 'saml', 'label' => Translations::get('sso.providers.saml')],
            ],
        ]);
    }

    public function update(UpsertOrganizationSsoRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $this->sso->upsert($organization, $request->user(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('sso.saved'),
        ]);

        return redirect()->route('settings.sso.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $this->sso->destroy($organization, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('sso.disconnected'),
        ]);

        return redirect()->route('settings.sso.edit');
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }
}

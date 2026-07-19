<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeleteOrganizationRequest;
use App\Http\Requests\Settings\UpdateOrganizationLocaleRequest;
use App\Services\ControlService;
use App\Services\OrganizationService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly ControlService $controls,
    ) {
    }

    public function update(UpdateOrganizationLocaleRequest $request): RedirectResponse
    {
        $organization = $request->organization();

        if ($organization === null) {
            abort(404);
        }

        $this->authorize('update', $organization);

        $previousLocale = $organization->resolvedLocale();
        $locale = $request->string('locale')->toString();

        $organization->update(['locale' => $locale]);

        if ($previousLocale !== $locale) {
            $this->controls->seedStarterCatalogue($organization->fresh(), refreshExisting: true);
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.organization_locale_updated'),
        ]);

        return back();
    }

    public function destroy(DeleteOrganizationRequest $request): RedirectResponse
    {
        $organization = $request->organization();

        if ($organization === null) {
            abort(404);
        }

        $this->authorize('delete', $organization);

        Auth::logout();

        $this->organizations->destroy($organization);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('settings.organization_deleted'),
        ]);

        return redirect()->route('home');
    }
}

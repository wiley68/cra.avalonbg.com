<?php

namespace App\Http\Controllers;

use App\Services\OrgOnboardingChecklistService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OnboardingChecklistController extends Controller
{
    public function __construct(
        private readonly OrgOnboardingChecklistService $checklist,
    ) {
    }

    public function dismiss(): RedirectResponse
    {
        $user = request()->user();
        $organization = $user?->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        $this->authorize('update', $organization);

        $this->checklist->dismiss($organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('dashboard.onboarding.dismissed'),
        ]);

        return back();
    }
}

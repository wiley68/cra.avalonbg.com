<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAppearanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Appearance', [
            'appearance' => $request->user()->appearance,
        ]);
    }

    public function update(UpdateAppearanceRequest $request): RedirectResponse
    {
        $request->user()->update([
            'appearance' => $request->validated('appearance'),
        ]);

        return to_route('appearance.edit');
    }
}

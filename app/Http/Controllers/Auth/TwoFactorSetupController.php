<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TwoFactorSetupController extends Controller
{
    public function __invoke(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
        ];

        $request->ensureStateIsValid();

        return Inertia::render('auth/TwoFactorSetup', $props);
    }
}


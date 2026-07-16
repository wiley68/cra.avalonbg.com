<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForcePasswordChangeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('auth/ForcePasswordChange');
    }

    public function update(ForcePasswordChangeRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->string('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return redirect()->route('dashboard');
    }
}


<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationSsoConnection;
use App\Services\OrganizationSsoService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SsoAuthController extends Controller
{
    public function __construct(
        private readonly OrganizationSsoService $sso,
    ) {
    }

    public function redirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email_or_slug' => ['required', 'string', 'max:255'],
        ]);

        $connection = $this->sso->findEnabledConnectionForLogin($validated['email_or_slug']);

        if ($connection === null) {
            throw ValidationException::withMessages([
                'email_or_slug' => Translations::get('sso.errors.not_available'),
            ]);
        }

        try {
            $login = $this->sso->beginLogin($connection);
        } catch (\RuntimeException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email_or_slug' => Translations::get('sso.errors.oidc_failed'),
            ]);
        }

        $request->session()->put('sso', [
            'state' => $login['state'],
            'nonce' => $login['nonce'],
            'organization_id' => $login['organization_id'],
        ]);

        return redirect()->away($login['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $session = $request->session()->pull('sso');

        if (
            !is_array($session)
            || empty($session['state'])
            || empty($session['nonce'])
            || empty($session['organization_id'])
        ) {
            return $this->failRedirect(Translations::get('sso.errors.session_expired'));
        }

        if ((string) $request->query('state') !== (string) $session['state']) {
            return $this->failRedirect(Translations::get('sso.errors.invalid_state'));
        }

        if ($request->filled('error')) {
            return $this->failRedirect(Translations::get('sso.errors.provider_denied'));
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->failRedirect(Translations::get('sso.errors.missing_code'));
        }

        $connection = OrganizationSsoConnection::query()
            ->with('organization')
            ->where('organization_id', (int) $session['organization_id'])
            ->where('is_enabled', true)
            ->first();

        if ($connection === null) {
            return $this->failRedirect(Translations::get('sso.errors.not_available'));
        }

        try {
            $this->sso->completeLogin($connection, $code, (string) $session['nonce']);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: Translations::get('sso.errors.login_failed');

            return $this->failRedirect((string) $message);
        } catch (\RuntimeException $exception) {
            report($exception);

            return $this->failRedirect(Translations::get('sso.errors.oidc_failed'));
        }

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    private function failRedirect(string $message): RedirectResponse
    {
        if (Auth::check()) {
            Auth::logout();
        }

        return redirect()
            ->route('login')
            ->withErrors(['email_or_slug' => $message]);
    }
}

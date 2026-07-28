<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationSsoConnection;
use App\Services\OrganizationSsoService;
use App\Services\SamlClient;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SsoAuthController extends Controller
{
    public function __construct(
        private readonly OrganizationSsoService $sso,
        private readonly SamlClient $saml,
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
                'email_or_slug' => Translations::get(
                    $connection->isSaml()
                    ? 'sso.errors.saml_failed'
                    : 'sso.errors.oidc_failed',
                ),
            ]);
        }

        $request->session()->put('sso', [
            'state' => $login['state'],
            'nonce' => $login['nonce'],
            'request_id' => $login['request_id'],
            'protocol' => $login['protocol'],
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
            || empty($session['organization_id'])
            || (($session['protocol'] ?? 'oidc') === 'saml')
        ) {
            return $this->failRedirect(Translations::get('sso.errors.session_expired'));
        }

        if (empty($session['nonce'])) {
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

    public function acs(Request $request): RedirectResponse
    {
        $session = $request->session()->pull('sso');
        $relayState = (string) $request->input('RelayState', '');

        if (
            !is_array($session)
            || ($session['protocol'] ?? null) !== 'saml'
            || empty($session['state'])
            || empty($session['organization_id'])
        ) {
            return $this->failRedirect(Translations::get('sso.errors.session_expired'));
        }

        if ($relayState === '' || $relayState !== (string) $session['state']) {
            return $this->failRedirect(Translations::get('sso.errors.invalid_state'));
        }

        $samlResponse = (string) $request->input('SAMLResponse', '');
        if ($samlResponse === '') {
            return $this->failRedirect(Translations::get('sso.errors.missing_saml_response'));
        }

        $connection = OrganizationSsoConnection::query()
            ->with('organization')
            ->where('organization_id', (int) $session['organization_id'])
            ->where('is_enabled', true)
            ->first();

        if ($connection === null || !$connection->isSaml()) {
            return $this->failRedirect(Translations::get('sso.errors.not_available'));
        }

        try {
            $this->sso->completeSamlLogin(
                $connection,
                $samlResponse,
                isset($session['request_id']) ? (string) $session['request_id'] : null,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: Translations::get('sso.errors.login_failed');

            return $this->failRedirect((string) $message);
        } catch (\RuntimeException $exception) {
            report($exception);

            return $this->failRedirect(Translations::get('sso.errors.saml_failed'));
        }

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    public function metadata(): SymfonyResponse
    {
        $xml = $this->saml->metadataXml();

        return response($xml, 200, [
            'Content-Type' => 'application/samlmetadata+xml; charset=UTF-8',
        ]);
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

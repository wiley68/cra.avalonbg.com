<?php

namespace App\Services;

use App\Enums\SsoProvider;
use App\Models\Organization;
use App\Models\OrganizationSsoConnection;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationSsoService
{
    public function __construct(
        private readonly OidcClient $oidc,
    ) {
    }

    public function connectionPayload(?OrganizationSsoConnection $connection): ?array
    {
        if ($connection === null) {
            return null;
        }

        return [
            'id' => $connection->id,
            'provider' => $connection->provider instanceof SsoProvider
                ? $connection->provider->value
                : (string) $connection->provider,
            'issuer' => $connection->issuer,
            'client_id' => $connection->client_id,
            'has_client_secret' => filled($connection->client_secret),
            'allowed_email_domains' => $connection->normalizedDomains(),
            'is_enabled' => (bool) $connection->is_enabled,
        ];
    }

    /**
     * @param  array{
     *     provider: string,
     *     issuer: string,
     *     client_id: string,
     *     client_secret?: string|null,
     *     allowed_email_domains?: list<string>|string|null,
     *     is_enabled?: bool
     * }  $input
     */
    public function upsert(Organization $organization, User $actor, array $input): OrganizationSsoConnection
    {
        if (!$organization->canUseSso()) {
            throw ValidationException::withMessages([
                'sso' => Translations::get('sso.errors.plan_not_allowed'),
            ]);
        }

        $domains = $this->normalizeDomainsInput($input['allowed_email_domains'] ?? []);

        if ($domains === []) {
            throw ValidationException::withMessages([
                'allowed_email_domains' => Translations::get('sso.errors.domains_required'),
            ]);
        }

        $connection = $organization->ssoConnection;

        $secret = $input['client_secret'] ?? null;
        if ($connection === null && !filled($secret)) {
            throw ValidationException::withMessages([
                'client_secret' => Translations::get('sso.errors.secret_required'),
            ]);
        }

        $attributes = [
            'provider' => SsoProvider::tryFrom((string) $input['provider'])?->value
                ?? SsoProvider::Generic->value,
            'issuer' => rtrim((string) $input['issuer'], '/'),
            'client_id' => (string) $input['client_id'],
            'allowed_email_domains' => $domains,
            'is_enabled' => (bool) ($input['is_enabled'] ?? false),
        ];

        if (filled($secret)) {
            $attributes['client_secret'] = (string) $secret;
        }

        $wasNew = $connection === null;

        $connection = DB::transaction(function () use ($organization, $connection, $attributes) {
            if ($connection === null) {
                return OrganizationSsoConnection::query()->create([
                    ...$attributes,
                    'organization_id' => $organization->id,
                ]);
            }

            $connection->update($attributes);

            return $connection->fresh() ?? $connection;
        });

        if ($wasNew) {
            AuditLogger::logSsoConnectionCreated($connection, $actor);
        } else {
            AuditLogger::logSsoConnectionUpdated($connection, $actor);
        }

        return $connection;
    }

    public function destroy(Organization $organization, User $actor): void
    {
        $connection = $organization->ssoConnection;

        if ($connection === null) {
            return;
        }

        $connectionId = $connection->id;
        $connection->delete();

        AuditLogger::logSsoConnectionDeleted($organization, $actor, $connectionId);
    }

    public function findEnabledConnectionForLogin(string $emailOrSlug): ?OrganizationSsoConnection
    {
        $value = strtolower(trim($emailOrSlug));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            $domain = substr(strrchr($value, '@') ?: '', 1);

            if ($domain === '') {
                return null;
            }

            $connections = OrganizationSsoConnection::query()
                ->with('organization')
                ->where('is_enabled', true)
                ->get();

            foreach ($connections as $connection) {
                $organization = $connection->organization;
                if ($organization === null || !$organization->canUseSso()) {
                    continue;
                }

                if ($connection->allowsEmailDomain($value)) {
                    return $connection;
                }
            }

            return null;
        }

        $organization = Organization::query()
            ->where('slug', $value)
            ->where('is_active', true)
            ->first();

        if ($organization === null || !$organization->canUseSso()) {
            return null;
        }

        $connection = $organization->ssoConnection;

        if ($connection === null || !$connection->is_enabled) {
            return null;
        }

        return $connection;
    }

    /**
     * @return array{url: string, state: string, nonce: string, organization_id: int}
     */
    public function beginLogin(OrganizationSsoConnection $connection): array
    {
        $organization = $connection->organization;

        if ($organization === null || !$organization->canUseSso() || !$connection->is_enabled) {
            throw ValidationException::withMessages([
                'sso' => Translations::get('sso.errors.not_available'),
            ]);
        }

        $discovery = $this->oidc->discover($connection->issuer);
        $state = $this->oidc->newState();
        $nonce = $this->oidc->newNonce();

        $url = $this->oidc->authorizationUrl(
            $discovery,
            $connection->client_id,
            route('auth.sso.callback', absolute: true),
            $state,
            $nonce,
        );

        return [
            'url' => $url,
            'state' => $state,
            'nonce' => $nonce,
            'organization_id' => $organization->id,
        ];
    }

    public function completeLogin(
        OrganizationSsoConnection $connection,
        string $code,
        string $expectedNonce,
    ): User {
        $organization = $connection->organization;

        if ($organization === null || !$organization->canUseSso() || !$connection->is_enabled) {
            throw ValidationException::withMessages([
                'sso' => Translations::get('sso.errors.not_available'),
            ]);
        }

        $discovery = $this->oidc->discover($connection->issuer);
        $tokens = $this->oidc->exchangeCode(
            $discovery,
            $connection->client_id,
            (string) $connection->client_secret,
            $code,
            route('auth.sso.callback', absolute: true),
        );

        $claims = [];
        if (filled($tokens['id_token'])) {
            $claims = $this->oidc->decodeIdTokenPayload((string) $tokens['id_token']);
            if (isset($claims['nonce']) && (string) $claims['nonce'] !== $expectedNonce) {
                throw ValidationException::withMessages([
                    'sso' => Translations::get('sso.errors.invalid_nonce'),
                ]);
            }
        }

        if (filled($tokens['access_token'])) {
            $userInfo = $this->oidc->userInfo($discovery, (string) $tokens['access_token']);
            $claims = array_merge($claims, $userInfo);
        }

        $email = strtolower(trim((string) ($claims['email'] ?? $claims['preferred_username'] ?? '')));

        if ($email === '' || !str_contains($email, '@')) {
            throw ValidationException::withMessages([
                'sso' => Translations::get('sso.errors.email_missing'),
            ]);
        }

        if (!$connection->allowsEmailDomain($email)) {
            throw ValidationException::withMessages([
                'sso' => Translations::get('sso.errors.domain_rejected'),
            ]);
        }

        $user = $organization->users()
            ->whereRaw('LOWER(users.email) = ?', [$email])
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'sso' => Translations::get('sso.errors.unknown_user'),
            ]);
        }

        Auth::login($user, true);
        AuditLogger::logSsoLoginSuccess($user, $organization);

        return $user;
    }

    /**
     * @param  list<string>|string|null  $input
     * @return list<string>
     */
    private function normalizeDomainsInput(array|string|null $input): array
    {
        if (is_string($input)) {
            $input = preg_split('/[\s,;]+/', $input) ?: [];
        }

        if (!is_array($input)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static function ($domain): string {
                $domain = strtolower(trim((string) $domain));
                $domain = ltrim($domain, '@');

                return $domain;
            },
            $input,
        ))));
    }
}

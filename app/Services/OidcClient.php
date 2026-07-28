<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OidcClient
{
    /**
     * @return array{
     *     authorization_endpoint: string,
     *     token_endpoint: string,
     *     userinfo_endpoint: string|null,
     *     jwks_uri: string|null,
     *     issuer: string
     * }
     */
    public function discover(string $issuer): array
    {
        $issuer = rtrim($issuer, '/');
        $url = $issuer . '/.well-known/openid-configuration';

        try {
            $response = Http::timeout(15)->acceptJson()->get($url)->throw()->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('OIDC discovery failed for issuer.', 0, $exception);
        }

        if (
            !is_array($response)
            || empty($response['authorization_endpoint'])
            || empty($response['token_endpoint'])
        ) {
            throw new RuntimeException('OIDC discovery document is incomplete.');
        }

        return [
            'authorization_endpoint' => (string) $response['authorization_endpoint'],
            'token_endpoint' => (string) $response['token_endpoint'],
            'userinfo_endpoint' => isset($response['userinfo_endpoint'])
                ? (string) $response['userinfo_endpoint']
                : null,
            'jwks_uri' => isset($response['jwks_uri']) ? (string) $response['jwks_uri'] : null,
            'issuer' => (string) ($response['issuer'] ?? $issuer),
        ];
    }

    /**
     * @param  array{authorization_endpoint: string}  $discovery
     */
    public function authorizationUrl(
        array $discovery,
        string $clientId,
        string $redirectUri,
        string $state,
        string $nonce,
        string $scopes = 'openid email profile',
    ): string {
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $state,
            'nonce' => $nonce,
            'response_mode' => 'query',
        ]);

        return $discovery['authorization_endpoint'] . '?' . $query;
    }

    /**
     * @param  array{token_endpoint: string}  $discovery
     * @return array{access_token: string|null, id_token: string|null, token_type: string|null}
     */
    public function exchangeCode(
        array $discovery,
        string $clientId,
        string $clientSecret,
        string $code,
        string $redirectUri,
    ): array {
        try {
            $response = Http::asForm()
                ->timeout(20)
                ->acceptJson()
                ->post($discovery['token_endpoint'], [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('OIDC token exchange failed.', 0, $exception);
        }

        if (!is_array($response)) {
            throw new RuntimeException('OIDC token response is invalid.');
        }

        return [
            'access_token' => isset($response['access_token']) ? (string) $response['access_token'] : null,
            'id_token' => isset($response['id_token']) ? (string) $response['id_token'] : null,
            'token_type' => isset($response['token_type']) ? (string) $response['token_type'] : null,
        ];
    }

    /**
     * @param  array{userinfo_endpoint: string|null}  $discovery
     * @return array<string, mixed>
     */
    public function userInfo(array $discovery, string $accessToken): array
    {
        if (empty($discovery['userinfo_endpoint'])) {
            return [];
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->acceptJson()
                ->get($discovery['userinfo_endpoint'])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('OIDC userinfo request failed.', 0, $exception);
        }

        return is_array($response) ? $response : [];
    }

    /**
     * Decode JWT payload without signature verification (tests / when JWKS unused).
     * Production flows should prefer userinfo when available.
     *
     * @return array<string, mixed>
     */
    public function decodeIdTokenPayload(string $idToken): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return [];
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        return is_array($payload) ? $payload : [];
    }

    public function newState(): string
    {
        return Str::random(40);
    }

    public function newNonce(): string
    {
        return Str::random(32);
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}

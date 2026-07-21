<?php

namespace App\Services\Vcs;

use App\Enums\VcsAuthType;
use App\Models\OrganizationVcsConnection;
use App\Support\Translations;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GitHubAppTokenService
{
    /**
     * Mint a short-lived installation access token for API calls.
     */
    public function installationAccessToken(OrganizationVcsConnection $connection): string
    {
        if ($connection->auth_type !== VcsAuthType::GithubApp) {
            throw new RuntimeException('Connection is not a GitHub App.');
        }

        $appId = $connection->github_app_id;
        $installationId = $connection->github_installation_id;
        $privateKey = $connection->github_private_key;

        if (!filled($appId) || !filled($installationId) || !filled($privateKey)) {
            throw new RuntimeException('GitHub App credentials are incomplete.');
        }

        $jwt = $this->createAppJwt($appId, $privateKey);

        $response = Http::withToken($jwt)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->post('https://api.github.com/app/installations/' . $installationId . '/access_tokens');

        if (!$response->successful()) {
            throw new RuntimeException(
                'Failed to mint GitHub App installation token (HTTP ' . $response->status() . ').',
            );
        }

        $token = $response->json('token');

        if (!is_string($token) || $token === '') {
            throw new RuntimeException('GitHub App installation token response was empty.');
        }

        return $token;
    }

    /**
     * Verify App credentials by minting a token and listing installation repositories.
     *
     * @throws ValidationException
     */
    public function verify(
        string $appId,
        string $installationId,
        string $privateKey,
    ): void {
        try {
            $jwt = $this->createAppJwt($appId, $privateKey);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'github_private_key' => [Translations::get('settings.integrations.github_app_private_key_invalid')],
            ]);
        }

        $tokenResponse = Http::withToken($jwt)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->post('https://api.github.com/app/installations/' . $installationId . '/access_tokens');

        if (!$tokenResponse->successful()) {
            throw ValidationException::withMessages([
                'github_installation_id' => [Translations::get('settings.integrations.github_app_invalid')],
            ]);
        }

        $token = $tokenResponse->json('token');

        if (!is_string($token) || $token === '') {
            throw ValidationException::withMessages([
                'github_installation_id' => [Translations::get('settings.integrations.github_app_invalid')],
            ]);
        }

        $reposResponse = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->get('https://api.github.com/installation/repositories', [
                'per_page' => 1,
            ]);

        if ($reposResponse->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'github_installation_id' => [Translations::get('settings.integrations.github_app_invalid')],
        ]);
    }

    private function createAppJwt(string $appId, string $privateKeyPem): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iat' => $now - 60,
            'exp' => $now + (9 * 60),
            'iss' => $appId,
        ], JSON_THROW_ON_ERROR));

        $data = $header . '.' . $payload;

        $key = openssl_pkey_get_private($privateKeyPem);

        if ($key === false) {
            throw new RuntimeException('Invalid GitHub App private key PEM.');
        }

        $signature = '';

        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign GitHub App JWT.');
        }

        return $data . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

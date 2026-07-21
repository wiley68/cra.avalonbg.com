<?php

namespace App\Services;

use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\User;
use App\Services\Vcs\GitHubAppTokenService;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VcsConnectionService
{
    public function __construct(
        private readonly GitHubAppTokenService $githubAppTokens,
    ) {
    }

    public function storeGithubPat(
        Organization $organization,
        User $actor,
        string $token,
        ?string $label = null,
    ): OrganizationVcsConnection {
        return $this->storePat(
            organization: $organization,
            actor: $actor,
            provider: VcsProvider::Github,
            token: $token,
            label: $label ?: 'GitHub',
            verify: fn(string $value) => $this->verifyGithubPat($value),
            clearGithubApp: true,
        );
    }

    public function storeGitlabPat(
        Organization $organization,
        User $actor,
        string $token,
        ?string $label = null,
    ): OrganizationVcsConnection {
        return $this->storePat(
            organization: $organization,
            actor: $actor,
            provider: VcsProvider::Gitlab,
            token: $token,
            label: $label ?: 'GitLab',
            verify: fn(string $value) => $this->verifyGitlabPat($value),
            clearGithubApp: false,
        );
    }

    public function storeGithubApp(
        Organization $organization,
        User $actor,
        string $appId,
        string $installationId,
        ?string $privateKey,
        ?string $label = null,
    ): OrganizationVcsConnection {
        $existing = OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', VcsProvider::Github)
            ->first();

        $resolvedPrivateKey = $privateKey;

        if (
            !filled($resolvedPrivateKey)
            && $existing !== null
            && $existing->auth_type === VcsAuthType::GithubApp
            && filled($existing->github_private_key)
        ) {
            $resolvedPrivateKey = $existing->github_private_key;
        }

        if (!filled($resolvedPrivateKey)) {
            throw ValidationException::withMessages([
                'github_private_key' => [Translations::get('settings.integrations.github_app_private_key_required')],
            ]);
        }

        $this->githubAppTokens->verify($appId, $installationId, $resolvedPrivateKey);

        $attributes = [
            'auth_type' => VcsAuthType::GithubApp,
            'token' => null,
            'github_app_id' => $appId,
            'github_installation_id' => $installationId,
            'github_private_key' => $resolvedPrivateKey,
            'label' => $label ?: 'GitHub App',
            'status' => VcsConnectionStatus::Active,
            'last_verified_at' => now(),
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $connection = $existing->fresh();
            AuditLogger::logVcsConnectionUpdated($connection, $actor);

            return $connection;
        }

        $connection = OrganizationVcsConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => VcsProvider::Github,
            'sync_schedule' => VcsSyncSchedule::Off,
            ...$attributes,
        ]);

        AuditLogger::logVcsConnectionCreated($connection, $actor);

        return $connection;
    }

    /**
     * @param  callable(string): void  $verify
     */
    private function storePat(
        Organization $organization,
        User $actor,
        VcsProvider $provider,
        string $token,
        string $label,
        callable $verify,
        bool $clearGithubApp,
    ): OrganizationVcsConnection {
        $verify($token);

        $existing = OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
            ->first();

        $attributes = [
            'auth_type' => VcsAuthType::Pat,
            'token' => $token,
            'label' => $label,
            'status' => VcsConnectionStatus::Active,
            'last_verified_at' => now(),
        ];

        if ($clearGithubApp) {
            $attributes['github_app_id'] = null;
            $attributes['github_installation_id'] = null;
            $attributes['github_private_key'] = null;
        }

        if ($existing !== null) {
            $existing->update($attributes);
            $connection = $existing->fresh();
            AuditLogger::logVcsConnectionUpdated($connection, $actor);

            return $connection;
        }

        $connection = OrganizationVcsConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => $provider,
            'sync_schedule' => VcsSyncSchedule::Off,
            ...$attributes,
        ]);

        AuditLogger::logVcsConnectionCreated($connection, $actor);

        return $connection;
    }

    public function updateSyncSchedule(
        OrganizationVcsConnection $connection,
        VcsSyncSchedule $schedule,
        User $actor,
    ): OrganizationVcsConnection {
        $connection->update([
            'sync_schedule' => $schedule,
        ]);

        $fresh = $connection->fresh();
        AuditLogger::logVcsConnectionUpdated($fresh, $actor);

        return $fresh;
    }

    public function rotateWebhookSecret(
        OrganizationVcsConnection $connection,
        User $actor,
    ): string {
        $plain = Str::random(48);

        $connection->update([
            'webhook_secret' => $plain,
        ]);

        AuditLogger::logVcsConnectionUpdated($connection->fresh(), $actor);

        return $plain;
    }

    public function delete(OrganizationVcsConnection $connection, User $actor): void
    {
        AuditLogger::logVcsConnectionDeleted($connection, $actor);
        $connection->delete();
    }

    private function verifyGithubPat(string $token): void
    {
        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->get('https://api.github.com/user');

        if ($response->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'token' => [Translations::get('settings.integrations.github_token_invalid')],
        ]);
    }

    private function verifyGitlabPat(string $token): void
    {
        $response = Http::withHeaders([
            'PRIVATE-TOKEN' => $token,
            'User-Agent' => 'CRA-Compliance-Workspace',
        ])
            ->acceptJson()
            ->get('https://gitlab.com/api/v4/user');

        if ($response->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'token' => [Translations::get('settings.integrations.gitlab_token_invalid')],
        ]);
    }
}

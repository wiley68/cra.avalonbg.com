<?php

namespace App\Services;

use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VcsConnectionService
{
    public function storeGithubPat(
        Organization $organization,
        User $actor,
        string $token,
        ?string $label = null,
    ): OrganizationVcsConnection {
        $this->verifyGithubPat($token);

        $existing = OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', VcsProvider::Github)
            ->first();

        $attributes = [
            'auth_type' => VcsAuthType::Pat,
            'token' => $token,
            'label' => $label ?: 'GitHub',
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
}

<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Support\AuditLogger;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class UserTwoFactorResetService
{
    public function __construct(
        private readonly DisableTwoFactorAuthentication $disableTwoFactor,
    ) {
    }

    public function reset(User $target, User $actor, Organization $organization): bool
    {
        if (!$target->hasEnabledTwoFactorAuthentication()) {
            return false;
        }

        ($this->disableTwoFactor)($target);

        AuditLogger::logTwoFactorReset(
            target: $target,
            actor: $actor,
            organizationId: $organization->id,
        );

        return true;
    }

    /**
     * Clear 2FA after a successful forgot-password reset (user likely lost authenticator access too).
     */
    public function resetAfterPasswordRecovery(User $user): bool
    {
        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return false;
        }

        ($this->disableTwoFactor)($user);

        $organizationId = $user->organizations()->orderBy('organizations.id')->value('organizations.id');

        AuditLogger::logTwoFactorReset(
            target: $user,
            actor: $user,
            organizationId: $organizationId !== null ? (int) $organizationId : null,
            via: 'password_recovery',
        );

        return true;
    }
}

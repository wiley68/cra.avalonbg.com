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
    ) {}

    public function reset(User $target, User $actor, Organization $organization): bool
    {
        if (! $target->hasEnabledTwoFactorAuthentication()) {
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
}

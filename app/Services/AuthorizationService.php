<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class AuthorizationService
{
    public function can(User $user, string $permission, ?Organization $organization = null): bool
    {
        if ($user->isSystemAdmin()) {
            return true;
        }

        return $user->hasPermission($permission, $organization);
    }
}


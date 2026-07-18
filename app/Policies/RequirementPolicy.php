<?php

namespace App\Policies;

use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, Requirement $requirement): bool
    {
        return $user->isPlatformAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, Requirement $requirement): bool
    {
        return $user->isPlatformAdmin();
    }

    public function delete(User $user, Requirement $requirement): bool
    {
        return $user->isPlatformAdmin();
    }
}

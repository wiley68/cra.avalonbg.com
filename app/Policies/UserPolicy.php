<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\Organization;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::UsersView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::UsersCreate->value, $organization);
    }

    public function update(User $user, User $targetUser, Organization $organization): bool
    {
        return $user->id === $targetUser->id
            || $user->hasPermission(PermissionSlug::UsersUpdate->value, $organization);
    }

    public function delete(User $user, User $targetUser, Organization $organization): bool
    {
        return $user->id !== $targetUser->id
            && $user->hasPermission(PermissionSlug::UsersDelete->value, $organization);
    }

    public function assignRoles(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::UsersAssignRoles->value, $organization)
            || $user->hasRole(RoleSlug::OrganizationOwner, $organization);
    }
}


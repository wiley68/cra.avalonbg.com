<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin()
            || $user->hasPermission(PermissionSlug::PlatformAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin()
            || $user->hasPermission(PermissionSlug::PlatformAdmin->value);
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isSystemAdmin()
            || $user->hasPermission(PermissionSlug::OrganizationsView->value, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSystemAdmin()
            || $user->hasPermission(PermissionSlug::OrganizationsManage->value, $organization);
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->update($user, $organization);
    }
}

<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::OrganizationsView->value, $organization);
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::OrganizationsManage->value, $organization);
    }
}


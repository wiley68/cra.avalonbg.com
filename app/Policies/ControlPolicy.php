<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Control;
use App\Models\Organization;
use App\Models\User;

class ControlPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ControlsView->value, $organization);
    }

    public function view(User $user, Control $control, Organization $organization): bool
    {
        return $control->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::ControlsView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ControlsManage->value, $organization);
    }

    public function update(User $user, Control $control, Organization $organization): bool
    {
        return $control->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::ControlsManage->value, $organization);
    }

    public function delete(User $user, Control $control, Organization $organization): bool
    {
        return $control->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::ControlsManage->value, $organization);
    }
}

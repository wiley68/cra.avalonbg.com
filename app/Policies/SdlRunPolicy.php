<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\SdlRun;
use App\Models\User;

class SdlRunPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function view(User $user, SdlRun $run, Organization $organization): bool
    {
        return $this->belongsToOrganization($run, $organization)
            && $user->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function update(User $user, SdlRun $run, Organization $organization): bool
    {
        return $this->belongsToOrganization($run, $organization)
            && $user->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function delete(User $user, SdlRun $run, Organization $organization): bool
    {
        return $this->belongsToOrganization($run, $organization)
            && $user->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    private function belongsToOrganization(SdlRun $run, Organization $organization): bool
    {
        return $run->organization_id === $organization->id;
    }
}

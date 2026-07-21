<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\User;

class OrgPolicyPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canViewProducts($organization);
    }

    public function view(User $user, OrgPolicy $orgPolicy, Organization $organization): bool
    {
        return $orgPolicy->organization_id === $organization->id
            && $user->canViewProducts($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageProducts($organization);
    }

    public function update(User $user, OrgPolicy $orgPolicy, Organization $organization): bool
    {
        return $orgPolicy->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    public function delete(User $user, OrgPolicy $orgPolicy, Organization $organization): bool
    {
        return $orgPolicy->organization_id === $organization->id
            && $user->canManageProducts($organization)
            && $orgPolicy->status !== \App\Enums\PolicyStatus::Approved;
    }
}

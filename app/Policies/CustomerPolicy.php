<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canViewProducts($organization);
    }

    public function view(User $user, Customer $customer, Organization $organization): bool
    {
        return $customer->organization_id === $organization->id
            && $user->canViewProducts($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageProducts($organization);
    }

    public function update(User $user, Customer $customer, Organization $organization): bool
    {
        return $customer->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    public function delete(User $user, Customer $customer, Organization $organization): bool
    {
        return $customer->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }
}

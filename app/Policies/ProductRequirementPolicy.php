<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\ProductRequirement;
use App\Models\User;

class ProductRequirementPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ProductsView->value, $organization)
            || $user->hasPermission(PermissionSlug::RequirementsView->value, $organization);
    }

    public function view(User $user, ProductRequirement $productRequirement, Organization $organization): bool
    {
        return $productRequirement->product->organization_id === $organization->id
            && $this->viewAny($user, $organization);
    }

    public function update(User $user, ProductRequirement $productRequirement, Organization $organization): bool
    {
        if ($productRequirement->product->organization_id !== $organization->id) {
            return false;
        }

        return $user->hasPermission(PermissionSlug::ProductsManage->value, $organization)
            || $user->hasPermission(PermissionSlug::RequirementsManage->value, $organization);
    }
}

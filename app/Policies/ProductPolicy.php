<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function view(User $user, Product $product, Organization $organization): bool
    {
        return $product->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function update(User $user, Product $product, Organization $organization): bool
    {
        return $product->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function delete(User $user, Product $product, Organization $organization): bool
    {
        return $product->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }
}

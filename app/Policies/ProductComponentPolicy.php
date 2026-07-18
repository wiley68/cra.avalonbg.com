<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\User;

class ProductComponentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ComponentsView->value, $organization);
    }

    public function view(User $user, ProductComponent $component, Organization $organization): bool
    {
        return $this->belongsToOrganization($component, $organization)
            && $user->hasPermission(PermissionSlug::ComponentsView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ComponentsManage->value, $organization);
    }

    public function update(User $user, ProductComponent $component, Organization $organization): bool
    {
        return $this->belongsToOrganization($component, $organization)
            && $user->hasPermission(PermissionSlug::ComponentsManage->value, $organization);
    }

    public function delete(User $user, ProductComponent $component, Organization $organization): bool
    {
        return $this->belongsToOrganization($component, $organization)
            && $user->hasPermission(PermissionSlug::ComponentsManage->value, $organization);
    }

    private function belongsToOrganization(ProductComponent $component, Organization $organization): bool
    {
        return Product::query()
            ->where('id', $component->product_id)
            ->where('organization_id', $organization->id)
            ->exists();
    }
}

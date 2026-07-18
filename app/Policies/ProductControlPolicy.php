<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\User;

class ProductControlPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ControlsView->value, $organization);
    }

    public function view(User $user, ProductControl $productControl, Organization $organization): bool
    {
        return $this->belongsToOrganization($productControl, $organization)
            && $user->hasPermission(PermissionSlug::ControlsView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::ControlsManage->value, $organization);
    }

    public function update(User $user, ProductControl $productControl, Organization $organization): bool
    {
        return $this->belongsToOrganization($productControl, $organization)
            && $user->hasPermission(PermissionSlug::ControlsManage->value, $organization);
    }

    public function delete(User $user, ProductControl $productControl, Organization $organization): bool
    {
        return $this->belongsToOrganization($productControl, $organization)
            && $user->hasPermission(PermissionSlug::ControlsManage->value, $organization);
    }

    private function belongsToOrganization(ProductControl $productControl, Organization $organization): bool
    {
        return Product::query()
            ->where('id', $productControl->product_id)
            ->where('organization_id', $organization->id)
            ->exists();
    }
}

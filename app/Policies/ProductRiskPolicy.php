<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\User;

class ProductRiskPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::RisksView->value, $organization);
    }

    public function view(User $user, ProductRisk $productRisk, Organization $organization): bool
    {
        return $this->belongsToOrganization($productRisk, $organization)
            && $user->hasPermission(PermissionSlug::RisksView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::RisksManage->value, $organization);
    }

    public function update(User $user, ProductRisk $productRisk, Organization $organization): bool
    {
        return $this->belongsToOrganization($productRisk, $organization)
            && $user->hasPermission(PermissionSlug::RisksManage->value, $organization);
    }

    public function delete(User $user, ProductRisk $productRisk, Organization $organization): bool
    {
        return $this->belongsToOrganization($productRisk, $organization)
            && $user->hasPermission(PermissionSlug::RisksManage->value, $organization);
    }

    private function belongsToOrganization(ProductRisk $productRisk, Organization $organization): bool
    {
        return Product::query()
            ->where('id', $productRisk->product_id)
            ->where('organization_id', $organization->id)
            ->exists();
    }
}

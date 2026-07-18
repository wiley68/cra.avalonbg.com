<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

class EvidencePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::EvidenceView->value, $organization);
    }

    public function view(User $user, Evidence $evidence, Organization $organization): bool
    {
        return $this->belongsToOrganization($evidence, $organization)
            && $user->hasPermission(PermissionSlug::EvidenceView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::EvidenceManage->value, $organization);
    }

    public function update(User $user, Evidence $evidence, Organization $organization): bool
    {
        return $this->belongsToOrganization($evidence, $organization)
            && $user->hasPermission(PermissionSlug::EvidenceManage->value, $organization);
    }

    public function delete(User $user, Evidence $evidence, Organization $organization): bool
    {
        return $this->belongsToOrganization($evidence, $organization)
            && $user->hasPermission(PermissionSlug::EvidenceManage->value, $organization);
    }

    public function download(User $user, Evidence $evidence, Organization $organization): bool
    {
        return $this->view($user, $evidence, $organization);
    }

    private function belongsToOrganization(Evidence $evidence, Organization $organization): bool
    {
        return $evidence->organization_id === $organization->id
            && Product::query()
                ->where('id', $evidence->product_id)
                ->where('organization_id', $organization->id)
                ->exists();
    }
}

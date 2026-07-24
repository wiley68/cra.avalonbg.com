<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;

class TechnicalDocumentationPackagePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::TechnicalDocumentationView->value, $organization);
    }

    public function view(
        User $user,
        TechnicalDocumentationPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::TechnicalDocumentationView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::TechnicalDocumentationManage->value, $organization);
    }

    public function update(
        User $user,
        TechnicalDocumentationPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::TechnicalDocumentationManage->value, $organization);
    }

    public function delete(
        User $user,
        TechnicalDocumentationPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::TechnicalDocumentationManage->value, $organization)
            && in_array($package->status, [
                TechnicalDocumentationStatus::Draft,
                TechnicalDocumentationStatus::UnderReview,
            ], true);
    }
}

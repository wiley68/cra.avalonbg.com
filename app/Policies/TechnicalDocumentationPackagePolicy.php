<?php

namespace App\Policies;

use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;

class TechnicalDocumentationPackagePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canViewProducts($organization);
    }

    public function view(
        User $user,
        TechnicalDocumentationPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $user->canViewProducts($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageProducts($organization);
    }

    public function update(
        User $user,
        TechnicalDocumentationPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    public function delete(
        User $user,
        TechnicalDocumentationPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $user->canManageProducts($organization)
            && in_array($package->status, [
                TechnicalDocumentationStatus::Draft,
                TechnicalDocumentationStatus::UnderReview,
            ], true);
    }
}

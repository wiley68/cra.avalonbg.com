<?php

namespace App\Policies;

use App\Enums\AuditorReviewPackageStatus;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Models\User;

class AuditorReviewPackagePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canViewProducts($organization);
    }

    public function view(User $user, AuditorReviewPackage $package, Organization $organization): bool
    {
        return $package->organization_id === $organization->id
            && $user->canViewProducts($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageProducts($organization);
    }

    public function update(User $user, AuditorReviewPackage $package, Organization $organization): bool
    {
        return $package->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    public function delete(User $user, AuditorReviewPackage $package, Organization $organization): bool
    {
        return $package->organization_id === $organization->id
            && $user->canManageProducts($organization)
            && $package->status === AuditorReviewPackageStatus::Draft;
    }

    public function share(User $user, AuditorReviewPackage $package, Organization $organization): bool
    {
        return $this->update($user, $package, $organization)
            && $package->status === AuditorReviewPackageStatus::Draft;
    }

    public function close(User $user, AuditorReviewPackage $package, Organization $organization): bool
    {
        return $this->update($user, $package, $organization)
            && $package->status === AuditorReviewPackageStatus::Shared;
    }
}

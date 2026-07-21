<?php

namespace App\Policies;

use App\Enums\AuditorFindingStatus;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\RoleSlug;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Models\User;

class AuditorFindingPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canViewProducts($organization);
    }

    public function view(
        User $user,
        AuditorFinding $finding,
        Organization $organization,
    ): bool {
        return $finding->package?->organization_id === $organization->id
            && $user->canViewProducts($organization);
    }

    public function create(
        User $user,
        AuditorReviewPackage $package,
        Organization $organization,
    ): bool {
        return $package->organization_id === $organization->id
            && $package->status === AuditorReviewPackageStatus::Shared
            && $user->hasRole(RoleSlug::Auditor, $organization);
    }

    public function update(
        User $user,
        AuditorFinding $finding,
        Organization $organization,
    ): bool {
        $package = $finding->package;

        return $package !== null
            && $package->organization_id === $organization->id
            && $package->status === AuditorReviewPackageStatus::Shared
            && $user->hasRole(RoleSlug::Auditor, $organization);
    }

    public function updateStatus(
        User $user,
        AuditorFinding $finding,
        Organization $organization,
    ): bool {
        $package = $finding->package;

        return $package !== null
            && $package->organization_id === $organization->id
            && $package->status !== AuditorReviewPackageStatus::Draft
            && $user->canManageProducts($organization);
    }

    public function delete(
        User $user,
        AuditorFinding $finding,
        Organization $organization,
    ): bool {
        $package = $finding->package;

        return $package !== null
            && $package->organization_id === $organization->id
            && $package->status === AuditorReviewPackageStatus::Shared
            && $finding->status === AuditorFindingStatus::Open
            && $user->hasRole(RoleSlug::Auditor, $organization);
    }
}

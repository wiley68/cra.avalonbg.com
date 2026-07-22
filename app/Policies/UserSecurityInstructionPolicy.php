<?php

namespace App\Policies;

use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserSecurityInstruction;

class UserSecurityInstructionPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->canViewProducts($organization);
    }

    public function view(User $user, UserSecurityInstruction $instruction, Organization $organization): bool
    {
        return $instruction->organization_id === $organization->id
            && $user->canViewProducts($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->canManageProducts($organization);
    }

    public function update(User $user, UserSecurityInstruction $instruction, Organization $organization): bool
    {
        return $instruction->organization_id === $organization->id
            && $user->canManageProducts($organization);
    }

    public function delete(User $user, UserSecurityInstruction $instruction, Organization $organization): bool
    {
        return $instruction->organization_id === $organization->id
            && $user->canManageProducts($organization)
            && in_array($instruction->status, [
                UserSecurityInstructionStatus::Draft,
                UserSecurityInstructionStatus::UnderReview,
            ], true);
    }

    public function export(
        User $user,
        UserSecurityInstruction $instruction,
        Organization $organization,
    ): bool {
        if (
            $instruction->organization_id !== $organization->id
            || !$user->canViewProducts($organization)
        ) {
            return false;
        }

        // Published/retired: any viewer. Draft/under_review: managers only.
        if (
            in_array($instruction->status, [
                UserSecurityInstructionStatus::Published,
                UserSecurityInstructionStatus::Retired,
            ], true)
        ) {
            return true;
        }

        return $user->canManageProducts($organization);
    }
}

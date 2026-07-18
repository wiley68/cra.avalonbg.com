<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $organization ??= $user->currentOrganization();

        return $organization !== null
            && $user->hasPermission(PermissionSlug::AuditView->value, $organization);
    }

    public function view(User $user, AuditLog $auditLog, ?Organization $organization = null): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $organization ??= $user->currentOrganization();

        return $organization !== null
            && $auditLog->organization_id === $organization->id
            && $user->hasPermission(PermissionSlug::AuditView->value, $organization);
    }
}

<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\ProductIncident;
use App\Models\User;

class ProductIncidentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::IncidentsView->value, $organization);
    }

    public function view(User $user, ProductIncident $incident, Organization $organization): bool
    {
        return $this->belongsToOrganization($incident, $organization)
            && $user->hasPermission(PermissionSlug::IncidentsView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::IncidentsManage->value, $organization);
    }

    public function update(User $user, ProductIncident $incident, Organization $organization): bool
    {
        return $this->belongsToOrganization($incident, $organization)
            && $user->hasPermission(PermissionSlug::IncidentsManage->value, $organization);
    }

    public function delete(User $user, ProductIncident $incident, Organization $organization): bool
    {
        return $this->belongsToOrganization($incident, $organization)
            && $user->hasPermission(PermissionSlug::IncidentsManage->value, $organization);
    }

    private function belongsToOrganization(ProductIncident $incident, Organization $organization): bool
    {
        return $incident->organization_id === $organization->id;
    }
}

<?php

namespace App\Policies;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::TasksView->value, $organization);
    }

    public function view(User $user, Task $task, Organization $organization): bool
    {
        return $this->belongsToOrganization($task, $organization)
            && $user->hasPermission(PermissionSlug::TasksView->value, $organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasPermission(PermissionSlug::TasksManage->value, $organization);
    }

    public function update(User $user, Task $task, Organization $organization): bool
    {
        return $this->belongsToOrganization($task, $organization)
            && $user->hasPermission(PermissionSlug::TasksManage->value, $organization);
    }

    public function delete(User $user, Task $task, Organization $organization): bool
    {
        return $this->belongsToOrganization($task, $organization)
            && $user->hasPermission(PermissionSlug::TasksManage->value, $organization);
    }

    public function approve(User $user, Task $task, Organization $organization): bool
    {
        return $this->belongsToOrganization($task, $organization)
            && $user->hasPermission(PermissionSlug::TasksApprove->value, $organization);
    }

    private function belongsToOrganization(Task $task, Organization $organization): bool
    {
        return $task->organization_id === $organization->id
            && Product::query()
                ->where('id', $task->product_id)
                ->where('organization_id', $organization->id)
                ->exists();
    }
}

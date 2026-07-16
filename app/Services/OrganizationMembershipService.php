<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Enums\RoleSlug;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrganizationMembershipService
{
    /**
     * @return Collection<int, Role>
     */
    public function organizationRoles(): Collection
    {
        return Role::query()
            ->where('scope', RoleScope::Organization->value)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * @return list<array{id: int, name: string, email: string, must_change_password: bool, is_system_admin: bool, role_id: int, role_slug: string}>
     */
    public function listMembers(Organization $organization): array
    {
        $roleMap = Role::query()->pluck('slug', 'id');

        return $organization->users()
            ->select('users.id', 'users.name', 'users.email', 'users.must_change_password', 'users.is_system_admin')
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'must_change_password' => (bool) $user->must_change_password,
                'is_system_admin' => (bool) $user->is_system_admin,
                'role_id' => (int) $user->pivot->role_id,
                'role_slug' => $roleMap[$user->pivot->role_id] ?? 'unknown',
            ])
            ->values()
            ->all();
    }

    public function assertOrganizationRole(int $roleId): void
    {
        $exists = Role::query()
            ->whereKey($roleId)
            ->where('scope', RoleScope::Organization->value)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'role_id' => __('validation.exists', ['attribute' => 'role_id']),
            ]);
        }
    }

    public function createAndAttach(
        Organization $organization,
        array $attributes,
        int $roleId,
        User $invitedBy,
    ): User {
        $this->assertOrganizationRole($roleId);

        $user = User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'must_change_password' => $attributes['must_change_password'] ?? true,
            'is_system_admin' => $attributes['is_system_admin'] ?? false,
            'email_verified_at' => now(),
        ]);

        $this->attach($organization, $user, $roleId, $invitedBy);

        return $user;
    }

    public function attach(
        Organization $organization,
        User $user,
        int $roleId,
        ?User $invitedBy = null,
    ): void {
        $this->assertOrganizationRole($roleId);

        $organization->users()->attach($user->id, [
            'role_id' => $roleId,
            'invited_by' => $invitedBy?->id,
            'joined_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function updateRole(Organization $organization, User $user, int $roleId): void
    {
        $this->assertOrganizationRole($roleId);
        $this->assertMembership($organization, $user);

        $currentRole = $user->roleIn($organization);

        if (
            $currentRole?->slug === RoleSlug::OrganizationOwner->value
            && $roleId !== $currentRole->id
            && $this->organizationOwnerCount($organization) <= 1
        ) {
            throw ValidationException::withMessages([
                'role_id' => Translations::get('users.errors.last_owner_role'),
            ]);
        }

        $organization->users()->updateExistingPivot($user->id, [
            'role_id' => $roleId,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function detach(Organization $organization, User $user): void
    {
        $this->assertMembership($organization, $user);

        $role = $user->roleIn($organization);

        if (
            $role?->slug === RoleSlug::OrganizationOwner->value
            && $this->organizationOwnerCount($organization) <= 1
        ) {
            throw ValidationException::withMessages([
                'user' => Translations::get('users.errors.last_owner_remove'),
            ]);
        }

        $organization->users()->detach($user->id);
    }

    public function assertMembership(Organization $organization, User $user): void
    {
        $isMember = $organization->users()
            ->where('users.id', $user->id)
            ->exists();

        if (! $isMember) {
            abort(404);
        }
    }

    public function organizationOwnerCount(Organization $organization): int
    {
        $ownerRoleId = Role::query()
            ->where('slug', RoleSlug::OrganizationOwner->value)
            ->value('id');

        if ($ownerRoleId === null) {
            return 0;
        }

        return $organization->users()
            ->wherePivot('role_id', $ownerRoleId)
            ->count();
    }
}

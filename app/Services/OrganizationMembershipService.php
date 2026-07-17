<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Enums\RoleSlug;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, must_change_password: bool, role_id: int, role_slug: string}>
     */
    public function paginateMembers(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'name',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.must_change_password',
                'organization_user.role_id',
                'roles.slug as role_slug',
            ])
            ->join('organization_user', 'organization_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'organization_user.role_id')
            ->where('organization_user.organization_id', $organization->id);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('roles.slug', 'like', "%{$search}%")
                    ->orWhere('roles.name', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('users.id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'users.id',
            'email' => 'users.email',
            'must_change_password' => 'users.must_change_password',
            'role_slug' => 'roles.slug',
            default => 'users.name',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'must_change_password' => (bool) $user->must_change_password,
                'role_id' => (int) $user->role_id,
                'role_slug' => (string) $user->role_slug,
            ]);
    }

    public function assertOrganizationRole(int $roleId): void
    {
        $exists = Role::query()
            ->whereKey($roleId)
            ->where('scope', RoleScope::Organization->value)
            ->exists();

        if (!$exists) {
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
            'is_platform_admin' => false,
        ]);

        // email_verified_at is not mass-assignable; mark invited users as verified.
        $user->forceFill(['email_verified_at' => now()])->save();

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

        if (!$isMember) {
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

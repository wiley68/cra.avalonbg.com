<?php

namespace App\Models;

use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property bool $must_change_password
 * @property Carbon|null $password_changed_at
 * @property bool $is_platform_admin
 * @property string $appearance
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'must_change_password',
    'password_changed_at',
    'is_platform_admin',
    'appearance',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role_id', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    public function currentOrganization(): ?Organization
    {
        return $this->organizations()
            ->select(
                'organizations.id',
                'organizations.name',
                'organizations.slug',
                'organizations.is_active',
                'organizations.locale',
            )
            ->first();
    }

    public function roleIn(Organization $organization): ?Role
    {
        $pivot = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first()?->pivot;

        return $pivot?->role_id ? Role::query()->find($pivot->role_id) : null;
    }

    public function canManageUsers(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::UsersView->value, $organization);
    }

    public function canViewProducts(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageProducts(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewRequirements(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::RequirementsView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageRequirements(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::RequirementsManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewControls(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::ControlsView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageControls(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::ControlsManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewRisks(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::RisksView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageRisks(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::RisksManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewComponents(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::ComponentsView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageComponents(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::ComponentsManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewVulnerabilities(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::VulnerabilitiesView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageVulnerabilities(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::VulnerabilitiesManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewEvidence(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::EvidenceView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageEvidence(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::EvidenceManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canViewTasks(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::TasksView->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsView->value, $organization);
    }

    public function canManageTasks(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::TasksManage->value, $organization)
            || $this->hasPermission(PermissionSlug::ProductsManage->value, $organization);
    }

    public function canApproveTasks(?Organization $organization = null): bool
    {
        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::TasksApprove->value, $organization);
    }

    public function canViewAudit(?Organization $organization = null): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        $organization ??= $this->currentOrganization();

        if ($organization === null) {
            return false;
        }

        return $this->hasPermission(PermissionSlug::AuditView->value, $organization);
    }

    public function canManageOrganizations(): bool
    {
        return $this->isPlatformAdmin()
            || $this->hasPermission(PermissionSlug::PlatformAdmin->value);
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function hasRole(RoleSlug $role, ?Organization $organization = null): bool
    {
        if ($this->isPlatformAdmin() && $role === RoleSlug::PlatformAdmin) {
            return true;
        }

        if (!$organization) {
            return false;
        }

        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivotIn('role_id', Role::query()->where('slug', $role->value)->pluck('id'))
            ->exists();
    }

    public function resolvedPermissions(?Organization $organization = null): array
    {
        if ($this->isPlatformAdmin()) {
            return $this->platformAdminPermissions();
        }

        if (!$organization) {
            return [];
        }

        $roleId = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first()?->pivot?->role_id;

        if (!$roleId) {
            return [];
        }

        return Role::query()
            ->with('permissions:id,slug')
            ->find($roleId)
            ?->permissions
            ->pluck('slug')
            ->all() ?? [];
    }

    public function hasPermission(string $permission, ?Organization $organization = null): bool
    {
        return in_array($permission, $this->resolvedPermissions($organization), true);
    }

    /**
     * @return list<string>
     */
    private function platformAdminPermissions(): array
    {
        $role = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->with('permissions:id,slug')
            ->first();

        if ($role === null) {
            return config('cra.roles.' . RoleSlug::PlatformAdmin->value . '.permissions', []);
        }

        return $role->permissions->pluck('slug')->all();
    }
}

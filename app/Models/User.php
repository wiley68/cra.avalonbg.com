<?php

namespace App\Models;

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
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
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
 * @property bool $is_system_admin
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
    'is_system_admin',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'is_system_admin' => 'boolean',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role_id', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    public function roleIn(Organization $organization): ?Role
    {
        $pivot = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first()?->pivot;

        return $pivot?->role_id ? Role::query()->find($pivot->role_id) : null;
    }

    public function isSystemAdmin(): bool
    {
        return (bool) $this->is_system_admin;
    }

    public function hasRole(RoleSlug $role, ?Organization $organization = null): bool
    {
        if ($this->isSystemAdmin() && $role === RoleSlug::Administrator) {
            return true;
        }

        if (! $organization) {
            return false;
        }

        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->wherePivotIn('role_id', Role::query()->where('slug', $role->value)->pluck('id'))
            ->exists();
    }

    public function resolvedPermissions(?Organization $organization = null): array
    {
        if ($this->isSystemAdmin()) {
            return Permission::query()->pluck('slug')->all();
        }

        if (! $organization) {
            return [];
        }

        $roleId = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first()?->pivot?->role_id;

        if (! $roleId) {
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
}

<?php

namespace App\Models;

use App\Enums\RoleScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name', 'description', 'scope', 'is_default'])]
class Role extends Model
{
    protected function scope(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => RoleScope::from($value),
            set: fn (RoleScope|string $value) => $value instanceof RoleScope ? $value->value : $value,
        );
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot(['organization_id', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }
}


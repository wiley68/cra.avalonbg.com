<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'is_active',
    'subscription_plan',
    'trial_ends_at',
    'billing_email',
    'locale',
])]
class Organization extends Model
{
    public const LOCALES = ['en', 'bg'];

    public const DEFAULT_LOCALE = 'en';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function resolvedLocale(): string
    {
        $locale = $this->locale ?: self::DEFAULT_LOCALE;

        return in_array($locale, self::LOCALES, true)
            ? $locale
            : self::DEFAULT_LOCALE;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role_id', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function controls(): HasMany
    {
        return $this->hasMany(Control::class);
    }

    public function vcsConnections(): HasMany
    {
        return $this->hasMany(OrganizationVcsConnection::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}

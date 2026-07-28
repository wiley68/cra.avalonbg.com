<?php

namespace App\Models;

use App\Enums\SubscriptionPlan;
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

    public function resolvedSubscriptionPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::fromStoredOrDefault($this->subscription_plan);
    }

    /**
     * Maximum products for the resolved plan; null = unlimited.
     */
    public function maxProducts(): ?int
    {
        return $this->resolvedSubscriptionPlan()->maxProducts();
    }

    public function productsCount(): int
    {
        if ($this->relationLoaded('products')) {
            return $this->products->count();
        }

        return $this->products()->count();
    }

    public function canAddProduct(): bool
    {
        $max = $this->maxProducts();

        if ($max === null) {
            return true;
        }

        return $this->productsCount() < $max;
    }

    /**
     * @return array{
     *     plan: string,
     *     max_products: int|null,
     *     used: int,
     *     can_create: bool
     * }
     */
    public function productQuotaPayload(): array
    {
        return [
            'plan' => $this->resolvedSubscriptionPlan()->value,
            'max_products' => $this->maxProducts(),
            'used' => $this->productsCount(),
            'can_create' => $this->canAddProduct(),
        ];
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

    public function integrations(): HasMany
    {
        return $this->hasMany(OrganizationIntegration::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function auditorReviewPackages(): HasMany
    {
        return $this->hasMany(AuditorReviewPackage::class);
    }

    public function userSecurityInstructions(): HasMany
    {
        return $this->hasMany(UserSecurityInstruction::class);
    }

    public function sdlRuns(): HasMany
    {
        return $this->hasMany(SdlRun::class);
    }

    public function technicalDocumentationPackages(): HasMany
    {
        return $this->hasMany(TechnicalDocumentationPackage::class);
    }
}

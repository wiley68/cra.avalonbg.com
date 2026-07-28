<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
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
    'billing_status',
    'billing_interval',
    'payment_method',
    'billing_activated_at',
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
            'billing_activated_at' => 'datetime',
            'billing_status' => BillingStatus::class,
            'billing_interval' => BillingInterval::class,
            'payment_method' => PaymentMethod::class,
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

    public function resolvedBillingStatus(): BillingStatus
    {
        if ($this->billing_status instanceof BillingStatus) {
            return $this->billing_status;
        }

        return BillingStatus::tryFrom((string) $this->billing_status) ?? BillingStatus::Active;
    }

    public function isBillingActive(): bool
    {
        return $this->resolvedBillingStatus() === BillingStatus::Active;
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
        if (!$this->isBillingActive()) {
            return false;
        }

        $max = $this->maxProducts();

        if ($max === null) {
            return true;
        }

        return $this->productsCount() < $max;
    }

    /**
     * @return array{
     *     plan: string,
     *     billing_status: string,
     *     max_products: int|null,
     *     used: int,
     *     can_create: bool
     * }
     */
    public function productQuotaPayload(): array
    {
        return [
            'plan' => $this->resolvedSubscriptionPlan()->value,
            'billing_status' => $this->resolvedBillingStatus()->value,
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

    public function bankPaymentRequests(): HasMany
    {
        return $this->hasMany(OrganizationBankPaymentRequest::class);
    }

    /** @return HasMany<OrganizationBillingDocument, $this> */
    public function billingDocuments(): HasMany
    {
        return $this->hasMany(OrganizationBillingDocument::class);
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

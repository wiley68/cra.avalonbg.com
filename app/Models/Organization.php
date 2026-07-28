<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionPlan;
use App\Support\Translations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    'promo_code',
    'billing_email',
    'stripe_customer_id',
    'stripe_subscription_id',
    'sso_enabled',
    'locale',
    'onboarding_checklist_dismissed_at',
    'billing_past_due_at',
])]
class Organization extends Model
{
    public const LOCALES = ['en', 'bg'];

    public const DEFAULT_LOCALE = 'en';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sso_enabled' => 'boolean',
            'trial_ends_at' => 'datetime',
            'billing_activated_at' => 'datetime',
            'onboarding_checklist_dismissed_at' => 'datetime',
            'billing_past_due_at' => 'datetime',
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
        $this->syncExpiredTrial();

        return $this->resolvedBillingStatus() === BillingStatus::Active;
    }

    public function hasConfirmedPaidSubscription(): bool
    {
        return $this->billing_activated_at !== null
            && $this->payment_method !== null;
    }

    public function isOnTrial(): bool
    {
        $this->syncExpiredTrial();

        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture()
            && !$this->hasConfirmedPaidSubscription()
            && $this->resolvedBillingStatus() === BillingStatus::Active;
    }

    /**
     * Convert unpaid expired trials to pending_payment. Confirmed paid orgs clear trial_ends_at.
     */
    public function syncExpiredTrial(): bool
    {
        if ($this->trial_ends_at === null) {
            return false;
        }

        if ($this->hasConfirmedPaidSubscription()) {
            if ($this->exists) {
                $this->forceFill(['trial_ends_at' => null])->save();
            } else {
                $this->trial_ends_at = null;
            }

            return false;
        }

        if ($this->trial_ends_at->isFuture()) {
            return false;
        }

        if ($this->resolvedBillingStatus() !== BillingStatus::Active) {
            return false;
        }

        if ($this->exists) {
            $this->forceFill([
                'billing_status' => BillingStatus::PendingPayment->value,
            ])->save();
        } else {
            $this->billing_status = BillingStatus::PendingPayment;
        }

        return true;
    }

    /**
     * Soft trial notice for shared Inertia layout.
     *
     * @return array{
     *     ends_at: string,
     *     days_remaining: int,
     *     promo_code: string|null,
     *     billing_href: string,
     *     can_convert: bool
     * }|null
     */
    public function trialNoticePayload(): ?array
    {
        if (!$this->isOnTrial() || $this->trial_ends_at === null) {
            return null;
        }

        $secondsRemaining = max(
            0,
            $this->trial_ends_at->getTimestamp() - now()->getTimestamp(),
        );
        $daysRemaining = (int) ceil($secondsRemaining / 86400);

        return [
            'ends_at' => $this->trial_ends_at->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'promo_code' => $this->promo_code,
            'billing_href' => route('settings.billing.edit'),
            'can_convert' => $this->resolvedSubscriptionPlan() !== SubscriptionPlan::Free,
        ];
    }

    public function isPastDue(): bool
    {
        return $this->resolvedBillingStatus() === BillingStatus::PastDue;
    }

    public function isBillingCancelled(): bool
    {
        return $this->resolvedBillingStatus() === BillingStatus::Cancelled;
    }

    public function isInBillingGrace(): bool
    {
        if (!$this->isPastDue()) {
            return false;
        }

        $graceDays = max(0, (int) config('billing.dunning.grace_days', 14));
        $since = $this->billing_past_due_at ?? $this->updated_at;

        if ($since === null) {
            return true;
        }

        return $since->copy()->addDays($graceDays)->isFuture();
    }

    /**
     * Soft dunning notice for shared Inertia layout (no data deletion).
     *
     * @return array{
     *     status: string,
     *     in_grace: bool,
     *     grace_ends_at: string|null,
     *     read_only_hint: bool,
     *     billing_href: string,
     *     can_manage_stripe: bool
     * }|null
     */
    public function billingNoticePayload(): ?array
    {
        $status = $this->resolvedBillingStatus();

        if ($status !== BillingStatus::PastDue && $status !== BillingStatus::Cancelled) {
            return null;
        }

        $graceEndsAt = null;
        $inGrace = false;
        $readOnlyHint = true;

        if ($status === BillingStatus::PastDue) {
            $graceDays = max(0, (int) config('billing.dunning.grace_days', 14));
            $since = $this->billing_past_due_at ?? $this->updated_at;
            $inGrace = $this->isInBillingGrace();
            $readOnlyHint = !$inGrace;

            if ($since !== null) {
                $graceEndsAt = $since->copy()->addDays($graceDays)->toIso8601String();
            }
        }

        return [
            'status' => $status->value,
            'in_grace' => $inGrace,
            'grace_ends_at' => $graceEndsAt,
            'read_only_hint' => $readOnlyHint,
            'billing_href' => route('settings.billing.edit'),
            'can_manage_stripe' => filled($this->stripe_customer_id)
                && $this->payment_method === PaymentMethod::Stripe
                && filled(config('billing.stripe.secret')),
        ];
    }

    /**
     * Maximum products for the resolved plan; null = unlimited.
     */
    public function maxProducts(): ?int
    {
        return $this->resolvedSubscriptionPlan()->maxProducts();
    }

    /**
     * Maximum seats (users) for the resolved plan; null = unlimited.
     */
    public function maxSeats(): ?int
    {
        return $this->resolvedSubscriptionPlan()->maxSeats();
    }

    public function productsCount(): int
    {
        if ($this->relationLoaded('products')) {
            return $this->products->count();
        }

        return $this->products()->count();
    }

    public function seatsCount(): int
    {
        if ($this->relationLoaded('users')) {
            return $this->users->count();
        }

        return $this->users()->count();
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

    public function canAddUser(): bool
    {
        $max = $this->maxSeats();

        if ($max !== null && $this->seatsCount() >= $max) {
            return false;
        }

        // First seat is always allowed (signup / admin bootstrap).
        if ($this->seatsCount() === 0) {
            return true;
        }

        return $this->isBillingActive();
    }

    /**
     * Paid plans include AI assistant features. Free is UI-visible but gated.
     */
    public function canUseAi(): bool
    {
        return $this->resolvedSubscriptionPlan() !== SubscriptionPlan::Free;
    }

    /**
     * Standard and Enterprise include OIDC SSO.
     */
    public function canUseSso(): bool
    {
        $plan = $this->resolvedSubscriptionPlan();

        return $plan === SubscriptionPlan::Enterprise
            || $plan === SubscriptionPlan::Standard;
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

    /**
     * @return array{
     *     plan: string,
     *     billing_status: string,
     *     max_seats: int|null,
     *     used: int,
     *     can_create: bool
     * }
     */
    public function seatQuotaPayload(): array
    {
        return [
            'plan' => $this->resolvedSubscriptionPlan()->value,
            'billing_status' => $this->resolvedBillingStatus()->value,
            'max_seats' => $this->maxSeats(),
            'used' => $this->seatsCount(),
            'can_create' => $this->canAddUser(),
        ];
    }

    /**
     * Combined usage snapshot for Billing / dashboard surfaces.
     *
     * @return array{
     *     plan: string,
     *     billing_status: string,
     *     products: array{max: int|null, used: int, can_create: bool},
     *     seats: array{max: int|null, used: int, can_create: bool}
     * }
     */
    public function usageDashboardPayload(): array
    {
        $products = $this->productQuotaPayload();
        $seats = $this->seatQuotaPayload();

        return [
            'plan' => $products['plan'],
            'billing_status' => $products['billing_status'],
            'products' => [
                'max' => $products['max_products'],
                'used' => $products['used'],
                'can_create' => $products['can_create'],
            ],
            'seats' => [
                'max' => $seats['max_seats'],
                'used' => $seats['used'],
                'can_create' => $seats['can_create'],
            ],
        ];
    }

    /**
     * Message when product create is blocked (quota or billing status).
     */
    public function productCreationBlockedMessage(): string
    {
        if ($this->isPastDue()) {
            return Translations::get(
                $this->isInBillingGrace()
                ? 'products.plan_past_due_grace'
                : 'products.plan_past_due_readonly',
            );
        }

        if ($this->isBillingCancelled()) {
            return Translations::get('products.plan_cancelled');
        }

        if ($this->resolvedBillingStatus() === BillingStatus::PendingPayment) {
            return Translations::get('products.plan_pending_payment');
        }

        return Translations::get('products.plan_product_limit', [
            'plan' => Translations::get(
                'billing.plans.' . $this->resolvedSubscriptionPlan()->value,
            ),
            'max' => (string) ($this->maxProducts() ?? 0),
        ]);
    }

    /**
     * Message when user create is blocked (seat quota or billing status).
     */
    public function userCreationBlockedMessage(): string
    {
        if ($this->isPastDue()) {
            return Translations::get(
                $this->isInBillingGrace()
                ? 'users.plan_past_due_grace'
                : 'users.plan_past_due_readonly',
            );
        }

        if ($this->isBillingCancelled()) {
            return Translations::get('users.plan_cancelled');
        }

        if ($this->resolvedBillingStatus() === BillingStatus::PendingPayment) {
            return Translations::get('users.plan_pending_payment');
        }

        return Translations::get('users.plan_seat_limit', [
            'plan' => Translations::get(
                'billing.plans.' . $this->resolvedSubscriptionPlan()->value,
            ),
            'max' => (string) ($this->maxSeats() ?? 0),
        ]);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role_id', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    public function ssoConnection(): HasOne
    {
        return $this->hasOne(OrganizationSsoConnection::class);
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

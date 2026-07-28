<?php

namespace App\Services;

use App\Enums\BankPaymentRequestStatus;
use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\OrganizationBankPaymentRequest;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeBillingService
{
    public function isConfigured(): bool
    {
        return filled(config('billing.stripe.secret'));
    }

    public function canStartCheckout(Organization $organization): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $plan = $organization->resolvedSubscriptionPlan();

        if ($plan === SubscriptionPlan::Free) {
            return false;
        }

        return !$organization->isBillingActive()
            || $organization->payment_method === PaymentMethod::Stripe;
    }

    /**
     * @return array{url: string, session_id: string}
     */
    public function createCheckoutSession(Organization $organization, User $actor): array
    {
        if (!$this->isConfigured()) {
            throw ValidationException::withMessages([
                'stripe' => Translations::get('billing.stripe.errors.not_configured'),
            ]);
        }

        $plan = $organization->resolvedSubscriptionPlan();

        if ($plan === SubscriptionPlan::Free) {
            throw ValidationException::withMessages([
                'subscription_plan' => Translations::get('billing.stripe.errors.free_no_checkout'),
            ]);
        }

        if ($organization->isBillingActive() && $organization->payment_method !== PaymentMethod::Stripe) {
            throw ValidationException::withMessages([
                'billing_status' => Translations::get('billing.stripe.errors.already_active_other'),
            ]);
        }

        $interval = $organization->billing_interval instanceof BillingInterval
            ? $organization->billing_interval
            : BillingInterval::Month;

        $session = $this->requestCheckoutSession(
            $this->buildCheckoutSessionParams($organization, $actor, $plan, $interval),
        );

        if ($session['url'] === '') {
            throw ValidationException::withMessages([
                'stripe' => Translations::get('billing.stripe.errors.session_failed'),
            ]);
        }

        AuditLogger::logStripeCheckoutStarted($organization, $actor, $session['id']);

        return [
            'url' => $session['url'],
            'session_id' => $session['id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutSessionParams(
        Organization $organization,
        User $actor,
        SubscriptionPlan $plan,
        BillingInterval $interval,
    ): array {
        $params = [
            'mode' => 'subscription',
            'success_url' => route('settings.billing.stripe.success', absolute: true)
                . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('settings.billing.stripe.cancel', absolute: true),
            'client_reference_id' => (string) $organization->id,
            'metadata' => [
                'organization_id' => (string) $organization->id,
                'subscription_plan' => $plan->value,
                'billing_interval' => $interval->value,
                'actor_user_id' => (string) $actor->id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'subscription_plan' => $plan->value,
                    'billing_interval' => $interval->value,
                ],
            ],
            'line_items' => [$this->lineItem($plan, $interval)],
        ];

        if (filled($organization->stripe_customer_id)) {
            $params['customer'] = $organization->stripe_customer_id;
        } elseif (filled($organization->billing_email)) {
            $params['customer_email'] = $organization->billing_email;
        } else {
            $params['customer_email'] = $actor->email;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id: string, url: string}
     */
    private function requestCheckoutSession(array $params): array
    {
        return app(StripeCheckoutGateway::class)->createSession($params);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function handleEvent(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? null;

        if (!is_array($object)) {
            return;
        }

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            'invoice.paid' => $this->handleInvoicePaid($object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($object),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function constructEvent(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('billing.stripe.webhook_secret', '');

        if ($secret === '') {
            throw new UnexpectedValueException('Stripe webhook secret is not configured.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (SignatureVerificationException $exception) {
            throw $exception;
        }

        return $event->toArray();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    public function handleCheckoutCompleted(array $session): void
    {
        if (($session['mode'] ?? null) !== 'subscription') {
            return;
        }

        $organization = $this->resolveOrganizationFromStripeObject($session);

        if ($organization === null) {
            return;
        }

        $plan = SubscriptionPlan::fromStoredOrDefault(
            $session['metadata']['subscription_plan']
            ?? $organization->subscription_plan,
        );
        $interval = BillingInterval::tryFrom(
            (string) ($session['metadata']['billing_interval']
                ?? $organization->billing_interval?->value
                ?? BillingInterval::Month->value),
        ) ?? BillingInterval::Month;

        $customerId = isset($session['customer']) ? (string) $session['customer'] : null;
        $subscriptionId = isset($session['subscription']) ? (string) $session['subscription'] : null;

        $this->activateStripeSubscription(
            $organization,
            $plan,
            $interval,
            $customerId,
            $subscriptionId,
            BillingStatus::Active,
        );
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function handleSubscriptionUpdated(array $subscription): void
    {
        $organization = $this->resolveOrganizationFromSubscription($subscription);

        if ($organization === null) {
            return;
        }

        $status = (string) ($subscription['status'] ?? '');
        $billingStatus = match ($status) {
            'active', 'trialing' => BillingStatus::Active,
            'past_due', 'unpaid' => BillingStatus::PastDue,
            'canceled', 'incomplete_expired' => BillingStatus::Cancelled,
            default => null,
        };

        if ($billingStatus === null) {
            return;
        }

        $plan = SubscriptionPlan::fromStoredOrDefault(
            $subscription['metadata']['subscription_plan'] ?? $organization->subscription_plan,
        );
        $interval = $this->intervalFromSubscription($subscription)
            ?? ($organization->billing_interval instanceof BillingInterval
                ? $organization->billing_interval
                : BillingInterval::Month);

        $this->applySubscriptionState(
            $organization,
            $billingStatus,
            $plan,
            $interval,
            isset($subscription['customer']) ? (string) $subscription['customer'] : $organization->stripe_customer_id,
            isset($subscription['id']) ? (string) $subscription['id'] : $organization->stripe_subscription_id,
        );
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function handleSubscriptionDeleted(array $subscription): void
    {
        $organization = $this->resolveOrganizationFromSubscription($subscription);

        if ($organization === null) {
            return;
        }

        $this->applySubscriptionState(
            $organization,
            BillingStatus::Cancelled,
            $organization->resolvedSubscriptionPlan(),
            $organization->billing_interval instanceof BillingInterval
            ? $organization->billing_interval
            : BillingInterval::Month,
            isset($subscription['customer']) ? (string) $subscription['customer'] : $organization->stripe_customer_id,
            isset($subscription['id']) ? (string) $subscription['id'] : $organization->stripe_subscription_id,
        );
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function handleInvoicePaid(array $invoice): void
    {
        $organization = $this->resolveOrganizationFromInvoice($invoice);

        if ($organization === null) {
            return;
        }

        if (
            $organization->resolvedBillingStatus() === BillingStatus::Active
            && $organization->payment_method === PaymentMethod::Stripe
        ) {
            AuditLogger::logStripeSubscriptionRenewed($organization);

            return;
        }

        $this->applySubscriptionState(
            $organization,
            BillingStatus::Active,
            $organization->resolvedSubscriptionPlan(),
            $organization->billing_interval instanceof BillingInterval
            ? $organization->billing_interval
            : BillingInterval::Month,
            isset($invoice['customer']) ? (string) $invoice['customer'] : $organization->stripe_customer_id,
            isset($invoice['subscription']) ? (string) $invoice['subscription'] : $organization->stripe_subscription_id,
        );
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    public function handleInvoicePaymentFailed(array $invoice): void
    {
        $organization = $this->resolveOrganizationFromInvoice($invoice);

        if ($organization === null) {
            return;
        }

        $this->applySubscriptionState(
            $organization,
            BillingStatus::PastDue,
            $organization->resolvedSubscriptionPlan(),
            $organization->billing_interval instanceof BillingInterval
            ? $organization->billing_interval
            : BillingInterval::Month,
            isset($invoice['customer']) ? (string) $invoice['customer'] : $organization->stripe_customer_id,
            isset($invoice['subscription']) ? (string) $invoice['subscription'] : $organization->stripe_subscription_id,
        );
    }

    private function activateStripeSubscription(
        Organization $organization,
        SubscriptionPlan $plan,
        BillingInterval $interval,
        ?string $customerId,
        ?string $subscriptionId,
        BillingStatus $status,
    ): void {
        $previousPlan = $organization->resolvedSubscriptionPlan()->value;

        DB::transaction(function () use ($organization, $plan, $interval, $customerId, $subscriptionId, $status): void {
            /** @var OrganizationBankPaymentRequest|null $pending */
            $pending = $organization->bankPaymentRequests()
                ->where('status', BankPaymentRequestStatus::Pending->value)
                ->latest('id')
                ->first();

            if ($pending !== null) {
                $pending->update([
                    'status' => BankPaymentRequestStatus::Cancelled->value,
                ]);
            }

            $organization->forceFill([
                'subscription_plan' => $plan->value,
                'billing_interval' => $interval->value,
                'billing_status' => $status->value,
                'payment_method' => PaymentMethod::Stripe->value,
                'billing_activated_at' => $organization->billing_activated_at ?? now(),
                'stripe_customer_id' => $customerId ?? $organization->stripe_customer_id,
                'stripe_subscription_id' => $subscriptionId ?? $organization->stripe_subscription_id,
            ])->save();
        });

        $fresh = $organization->fresh();
        AuditLogger::logSubscriptionPlanChanged(
            $fresh,
            $previousPlan,
            $plan->value,
            null,
            'stripe',
        );
        AuditLogger::logBillingActivated($fresh, null, null);
        AuditLogger::logStripeSubscriptionUpdated($fresh, $status);
    }

    private function applySubscriptionState(
        Organization $organization,
        BillingStatus $status,
        SubscriptionPlan $plan,
        BillingInterval $interval,
        ?string $customerId,
        ?string $subscriptionId,
    ): void {
        $previousPlan = $organization->resolvedSubscriptionPlan()->value;

        $organization->forceFill([
            'subscription_plan' => $plan->value,
            'billing_interval' => $interval->value,
            'billing_status' => $status->value,
            'payment_method' => PaymentMethod::Stripe->value,
            'billing_activated_at' => $status === BillingStatus::Active
                ? ($organization->billing_activated_at ?? now())
                : $organization->billing_activated_at,
            'stripe_customer_id' => $customerId ?? $organization->stripe_customer_id,
            'stripe_subscription_id' => $subscriptionId ?? $organization->stripe_subscription_id,
        ])->save();

        $fresh = $organization->fresh();
        AuditLogger::logSubscriptionPlanChanged(
            $fresh,
            $previousPlan,
            $plan->value,
            null,
            'stripe',
        );
        AuditLogger::logStripeSubscriptionUpdated($fresh, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(SubscriptionPlan $plan, BillingInterval $interval): array
    {
        $configuredPriceId = (string) config(
            'billing.stripe.prices.' . $plan->value . '.' . $interval->value,
            '',
        );

        if ($configuredPriceId !== '') {
            return [
                'price' => $configuredPriceId,
                'quantity' => 1,
            ];
        }

        $amountEur = $interval === BillingInterval::Year
            ? ($plan->yearlyPriceEur() ?? ($plan->monthlyPriceEur() * 12))
            : $plan->monthlyPriceEur();

        return [
            'price_data' => [
                'currency' => (string) config('billing.stripe.currency', 'eur'),
                'unit_amount' => (int) round($amountEur * 100),
                'recurring' => [
                    'interval' => $interval === BillingInterval::Year ? 'year' : 'month',
                ],
                'product_data' => [
                    'name' => sprintf('CRA %s (%s)', ucfirst($plan->value), $interval->value),
                ],
            ],
            'quantity' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function resolveOrganizationFromStripeObject(array $object): ?Organization
    {
        $organizationId = $object['metadata']['organization_id']
            ?? $object['client_reference_id']
            ?? null;

        if (filled($organizationId) && ctype_digit((string) $organizationId)) {
            return Organization::query()->find((int) $organizationId);
        }

        $customerId = isset($object['customer']) ? (string) $object['customer'] : null;

        if (filled($customerId)) {
            return Organization::query()
                ->where('stripe_customer_id', $customerId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function resolveOrganizationFromSubscription(array $subscription): ?Organization
    {
        if (isset($subscription['id'])) {
            $bySubscription = Organization::query()
                ->where('stripe_subscription_id', (string) $subscription['id'])
                ->first();

            if ($bySubscription !== null) {
                return $bySubscription;
            }
        }

        return $this->resolveOrganizationFromStripeObject($subscription);
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function resolveOrganizationFromInvoice(array $invoice): ?Organization
    {
        if (isset($invoice['subscription'])) {
            $bySubscription = Organization::query()
                ->where('stripe_subscription_id', (string) $invoice['subscription'])
                ->first();

            if ($bySubscription !== null) {
                return $bySubscription;
            }
        }

        return $this->resolveOrganizationFromStripeObject($invoice);
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function intervalFromSubscription(array $subscription): ?BillingInterval
    {
        if (isset($subscription['metadata']['billing_interval'])) {
            return BillingInterval::tryFrom((string) $subscription['metadata']['billing_interval']);
        }

        $interval = $subscription['items']['data'][0]['price']['recurring']['interval'] ?? null;

        return match ($interval) {
            'year' => BillingInterval::Year,
            'month' => BillingInterval::Month,
            default => null,
        };
    }
}

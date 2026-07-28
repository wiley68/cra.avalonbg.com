<?php

namespace App\Services;

use App\Enums\BankPaymentRequestStatus;
use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantBillingService
{
    public function __construct(
        private readonly BankPaymentService $bankPayments,
    ) {
    }

    public function canManageStripe(Organization $organization): bool
    {
        return app(StripeBillingService::class)->isConfigured()
            && filled($organization->stripe_customer_id)
            && $organization->payment_method === PaymentMethod::Stripe;
    }

    /**
     * In-app plan changes are for non–Stripe-managed orgs.
     * Stripe subscriptions change via Customer Portal.
     */
    public function canChangePlanInApp(Organization $organization): bool
    {
        return !$this->canManageStripe($organization);
    }

    public function changePlan(
        Organization $organization,
        User $actor,
        SubscriptionPlan $plan,
        ?BillingInterval $interval,
    ): Organization {
        if (!$this->canChangePlanInApp($organization)) {
            throw ValidationException::withMessages([
                'subscription_plan' => Translations::get('billing.change_plan.errors.use_stripe_portal'),
            ]);
        }

        if ($plan === SubscriptionPlan::Free) {
            $interval = null;
        } elseif ($interval === null) {
            throw ValidationException::withMessages([
                'billing_interval' => Translations::get('billing.change_plan.errors.interval_required'),
            ]);
        }

        $previousPlan = $organization->resolvedSubscriptionPlan()->value;

        DB::transaction(function () use ($organization, $plan, $interval): void {
            if ($plan === SubscriptionPlan::Free) {
                $this->cancelPendingBankRequests($organization);

                $organization->forceFill([
                    'subscription_plan' => $plan->value,
                    'billing_interval' => null,
                    'billing_status' => BillingStatus::Active->value,
                    'payment_method' => null,
                    'billing_activated_at' => $organization->billing_activated_at ?? now(),
                ])->save();

                return;
            }

            $pending = $this->bankPayments->pendingRequest($organization);

            if ($pending !== null) {
                $pending->update([
                    'subscription_plan' => $plan->value,
                    'billing_interval' => $interval->value,
                    'amount_eur' => $this->bankPayments->amountEur($plan, $interval),
                ]);
            }

            $organization->forceFill([
                'subscription_plan' => $plan->value,
                'billing_interval' => $interval->value,
                'billing_status' => BillingStatus::PendingPayment->value,
                'billing_activated_at' => null,
                'payment_method' => null,
            ])->save();
        });

        $fresh = $organization->fresh();

        AuditLogger::logSubscriptionPlanChanged(
            $fresh,
            $previousPlan,
            $plan->value,
            $actor,
            'tenant',
        );

        return $fresh;
    }

    private function cancelPendingBankRequests(Organization $organization): void
    {
        $organization->bankPaymentRequests()
            ->where('status', BankPaymentRequestStatus::Pending->value)
            ->update(['status' => BankPaymentRequestStatus::Cancelled->value]);
    }
}

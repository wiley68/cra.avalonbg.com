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

class BankPaymentService
{
    public function amountEur(SubscriptionPlan $plan, BillingInterval $interval): float
    {
        if ($plan === SubscriptionPlan::Free) {
            return 0.0;
        }

        if ($interval === BillingInterval::Year) {
            return (float) ($plan->yearlyPriceEur() ?? 0);
        }

        return $plan->monthlyPriceEur();
    }

    public function bankInstructions(): array
    {
        return [
            'beneficiary' => (string) config('billing.bank.beneficiary', ''),
            'iban' => (string) config('billing.bank.iban', ''),
            'bic' => (string) config('billing.bank.bic', ''),
            'bank_name' => (string) config('billing.bank.bank_name', ''),
            'reference_prefix' => (string) config('billing.bank.reference_prefix', 'CRA'),
        ];
    }

    public function pendingRequest(Organization $organization): ?OrganizationBankPaymentRequest
    {
        return $organization->bankPaymentRequests()
            ->where('status', BankPaymentRequestStatus::Pending->value)
            ->latest('id')
            ->first();
    }

    public function createRequest(
        Organization $organization,
        User $actor,
        ?string $notes = null,
    ): OrganizationBankPaymentRequest {
        $plan = $organization->resolvedSubscriptionPlan();

        if ($plan === SubscriptionPlan::Free) {
            throw ValidationException::withMessages([
                'subscription_plan' => Translations::get('billing.errors.free_no_payment'),
            ]);
        }

        if ($organization->isBillingActive()) {
            throw ValidationException::withMessages([
                'billing_status' => Translations::get('billing.errors.already_active'),
            ]);
        }

        if ($this->pendingRequest($organization) !== null) {
            throw ValidationException::withMessages([
                'bank_payment' => Translations::get('billing.errors.pending_exists'),
            ]);
        }

        $interval = $organization->billing_interval instanceof BillingInterval
            ? $organization->billing_interval
            : BillingInterval::Month;

        $request = OrganizationBankPaymentRequest::query()->create([
            'organization_id' => $organization->id,
            'subscription_plan' => $plan->value,
            'billing_interval' => $interval->value,
            'amount_eur' => $this->amountEur($plan, $interval),
            'currency' => 'EUR',
            'payment_reference' => $this->makeReference($organization),
            'status' => BankPaymentRequestStatus::Pending->value,
            'requested_by' => $actor->id,
            'notes' => $notes,
        ]);

        AuditLogger::logBankPaymentRequested($request, $actor);

        return $request;
    }

    public function activate(
        Organization $organization,
        User $actor,
        ?OrganizationBankPaymentRequest $request = null,
        PaymentMethod $paymentMethod = PaymentMethod::Bank,
    ): Organization {
        $request ??= $this->pendingRequest($organization);

        return DB::transaction(function () use ($organization, $actor, $request, $paymentMethod): Organization {
            $previousPlan = $organization->resolvedSubscriptionPlan()->value;

            if ($request !== null) {
                if ($request->organization_id !== $organization->id) {
                    abort(404);
                }

                if (!$request->isPending()) {
                    throw ValidationException::withMessages([
                        'bank_payment' => Translations::get('billing.errors.request_not_pending'),
                    ]);
                }

                $request->update([
                    'status' => BankPaymentRequestStatus::Paid->value,
                    'activated_by' => $actor->id,
                    'activated_at' => now(),
                ]);

                $organization->subscription_plan = $request->subscription_plan;
                $organization->billing_interval = $request->billing_interval;
            }

            $organization->forceFill([
                'billing_status' => BillingStatus::Active->value,
                'payment_method' => $paymentMethod->value,
                'billing_activated_at' => now(),
                'trial_ends_at' => null,
                'billing_past_due_at' => null,
            ])->save();

            $fresh = $organization->fresh();
            AuditLogger::logSubscriptionPlanChanged(
                $fresh,
                $previousPlan,
                $fresh->resolvedSubscriptionPlan()->value,
                $actor,
                'bank_activate',
            );
            AuditLogger::logBillingActivated($fresh, $actor, $request);

            return $fresh;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(?OrganizationBankPaymentRequest $request): ?array
    {
        if ($request === null) {
            return null;
        }

        return [
            'id' => $request->id,
            'subscription_plan' => $request->subscription_plan,
            'billing_interval' => $request->billing_interval instanceof BillingInterval
                ? $request->billing_interval->value
                : (string) $request->billing_interval,
            'amount_eur' => (float) $request->amount_eur,
            'currency' => $request->currency,
            'payment_reference' => $request->payment_reference,
            'status' => $request->status instanceof BankPaymentRequestStatus
                ? $request->status->value
                : (string) $request->status,
            'created_at' => $request->created_at?->toIso8601String(),
            'activated_at' => $request->activated_at?->toIso8601String(),
            'notes' => $request->notes,
        ];
    }

    private function makeReference(Organization $organization): string
    {
        $prefix = (string) config('billing.bank.reference_prefix', 'CRA');

        return sprintf(
            '%s-%d-%s',
            strtoupper($prefix),
            $organization->id,
            strtoupper(bin2hex(random_bytes(3))),
        );
    }
}

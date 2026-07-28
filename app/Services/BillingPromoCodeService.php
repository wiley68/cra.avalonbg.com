<?php

namespace App\Services;

use App\Enums\BankPaymentRequestStatus;
use App\Enums\BillingStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingPromoCodeService
{
    public function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * @return array{code: string, trial_days: int, plans: list<string>|null}|null
     */
    public function resolve(?string $code): ?array
    {
        if (!filled($code)) {
            return null;
        }

        $normalized = $this->normalize($code);
        /** @var array<string, mixed>|null $entry */
        $entry = config('billing.promo_codes.' . $normalized);

        if (!is_array($entry)) {
            foreach ((array) config('billing.promo_codes', []) as $key => $candidate) {
                if (is_string($key) && strtoupper($key) === $normalized && is_array($candidate)) {
                    $entry = $candidate;
                    break;
                }
            }
        }

        if (!is_array($entry) || !(bool) ($entry['active'] ?? true)) {
            return null;
        }

        $trialDays = (int) ($entry['trial_days'] ?? 0);

        if ($trialDays < 1) {
            return null;
        }

        $plans = $entry['plans'] ?? null;

        if ($plans !== null && !is_array($plans)) {
            return null;
        }

        /** @var list<string>|null $planList */
        $planList = $plans === null
            ? null
            : array_values(array_map(static fn($plan): string => (string) $plan, $plans));

        return [
            'code' => $normalized,
            'trial_days' => $trialDays,
            'plans' => $planList,
        ];
    }

    /**
     * @return array{code: string, trial_days: int, plans: list<string>|null}
     */
    public function assertApplicable(?string $code, SubscriptionPlan $plan): array
    {
        if ($plan === SubscriptionPlan::Free) {
            throw ValidationException::withMessages([
                'promo_code' => Translations::get('billing.promo.errors.free_plan'),
            ]);
        }

        if (!filled($code)) {
            throw ValidationException::withMessages([
                'promo_code' => Translations::get('billing.promo.errors.required'),
            ]);
        }

        $resolved = $this->resolve($code);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'promo_code' => Translations::get('billing.promo.errors.invalid'),
            ]);
        }

        if (
            is_array($resolved['plans'])
            && !in_array($plan->value, $resolved['plans'], true)
        ) {
            throw ValidationException::withMessages([
                'promo_code' => Translations::get('billing.promo.errors.plan_not_allowed'),
            ]);
        }

        return $resolved;
    }

    /**
     * Start / refresh an unpaid trial from a resolved promo.
     *
     * @param  array{code: string, trial_days: int, plans: list<string>|null}  $promo
     */
    public function startTrial(Organization $organization, array $promo): Organization
    {
        return DB::transaction(function () use ($organization, $promo): Organization {
            $organization->bankPaymentRequests()
                ->where('status', BankPaymentRequestStatus::Pending->value)
                ->update(['status' => BankPaymentRequestStatus::Cancelled->value]);

            $organization->forceFill([
                'billing_status' => BillingStatus::Active->value,
                'trial_ends_at' => now()->addDays($promo['trial_days']),
                'promo_code' => $promo['code'],
                'billing_activated_at' => null,
                'payment_method' => null,
                'billing_past_due_at' => null,
            ])->save();

            return $organization->fresh() ?? $organization;
        });
    }
}

<?php

namespace App\Services;

use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\RoleSlug;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationRegistrationService
{
    public function __construct(
        private readonly OrganizationMembershipService $memberships,
        private readonly ControlService $controls,
        private readonly BankPaymentService $bankPayments,
    ) {
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     organization_name: string,
     *     organization_slug?: string|null,
     *     subscription_plan: string,
     *     billing_interval?: string|null,
     *     billing_email?: string|null,
     *     locale?: string|null
     * }  $input
     */
    public function register(array $input): User
    {
        $plan = SubscriptionPlan::fromStoredOrDefault($input['subscription_plan'] ?? null);
        $locale = in_array($input['locale'] ?? null, Organization::LOCALES, true)
            ? $input['locale']
            : Organization::DEFAULT_LOCALE;

        $slug = $this->uniqueSlug(
            filled($input['organization_slug'] ?? null)
            ? (string) $input['organization_slug']
            : (string) $input['organization_name'],
        );

        $billingStatus = $plan === SubscriptionPlan::Free
            ? BillingStatus::Active
            : BillingStatus::PendingPayment;

        $interval = null;
        if ($plan !== SubscriptionPlan::Free) {
            $interval = BillingInterval::tryFrom((string) ($input['billing_interval'] ?? BillingInterval::Month->value))
                ?? BillingInterval::Month;
        }

        return DB::transaction(function () use ($input, $plan, $locale, $slug, $billingStatus, $interval): User {
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'must_change_password' => false,
                'is_platform_admin' => false,
            ]);

            $organization = Organization::query()->create([
                'name' => $input['organization_name'],
                'slug' => $slug,
                'is_active' => true,
                'subscription_plan' => $plan->value,
                'billing_status' => $billingStatus->value,
                'billing_interval' => $interval?->value,
                'billing_email' => filled($input['billing_email'] ?? null)
                    ? $input['billing_email']
                    : $input['email'],
                'locale' => $locale,
            ]);

            $ownerRoleId = Role::query()
                ->where('slug', RoleSlug::OrganizationOwner->value)
                ->value('id');

            if ($ownerRoleId === null) {
                abort(500, 'Organization owner role is missing.');
            }

            $this->memberships->attach($organization, $user, (int) $ownerRoleId);
            $this->controls->seedStarterCatalogue($organization, refreshExisting: false);

            if ($billingStatus === BillingStatus::PendingPayment) {
                $this->bankPayments->createRequest($organization, $user);
            }

            return $user;
        });
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;
        $suffix = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}

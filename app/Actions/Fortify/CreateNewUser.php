<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationRegistrationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;
    use ProfileValidationRules;

    public function __construct(
        private readonly OrganizationRegistrationService $registrations,
    ) {
    }

    /**
     * Validate and create a newly registered user + organization.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        if (filled($input['organization_slug'] ?? null)) {
            $input['organization_slug'] = Str::slug((string) $input['organization_slug']);
        } elseif (filled($input['organization_name'] ?? null)) {
            $input['organization_slug'] = Str::slug((string) $input['organization_name']);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_slug' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            'subscription_plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'billing_interval' => [
                'nullable',
                Rule::enum(BillingInterval::class),
                Rule::requiredIf(fn() => ($input['subscription_plan'] ?? null) !== SubscriptionPlan::Free->value),
            ],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'locale' => ['nullable', 'string', Rule::in(Organization::LOCALES)],
            'promo_code' => ['nullable', 'string', 'max:64'],
        ])->validate();

        return $this->registrations->register($input);
    }
}

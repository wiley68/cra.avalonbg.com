<?php

namespace App\Http\Requests\Admin;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization !== null
            && ($this->user()?->can('update', $organization) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('slug')),
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = (int) $this->route('organization')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('organizations', 'slug')->ignore($organizationId),
            ],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'subscription_plan' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'locale' => ['required', 'string', Rule::in(Organization::LOCALES)],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Organization::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('slug')),
            ]);
        } elseif ($this->filled('name')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('name')),
            ]);
        }

        $this->merge([
            'create_owner' => $this->boolean('create_owner'),
            'seed_starter_controls' => $this->boolean('seed_starter_controls', true),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('organizations', 'slug')],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'subscription_plan' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'create_owner' => ['boolean'],
            'seed_starter_controls' => ['boolean'],
        ];

        if ($this->boolean('create_owner')) {
            $rules['owner_name'] = ['required', 'string', 'max:255'];
            $rules['owner_email'] = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['owner_password'] = ['required', 'string', $this->passwordRule(), 'confirmed'];
        }

        return $rules;
    }
}

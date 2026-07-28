<?php

namespace App\Http\Requests\Settings;

use App\Enums\SsoProvider;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertOrganizationSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();

        return $organization instanceof Organization
            && ($this->user()?->can('update', $organization) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(SsoProvider::class)],
            'issuer' => ['required', 'string', 'max:500', 'url'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'allowed_email_domains' => ['required', 'string', 'max:2000'],
            'is_enabled' => ['boolean'],
        ];
    }
}

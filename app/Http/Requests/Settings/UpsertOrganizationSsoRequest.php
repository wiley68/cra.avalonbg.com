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
        $isSaml = $this->input('provider') === SsoProvider::Saml->value;

        return [
            'provider' => ['required', Rule::enum(SsoProvider::class)],
            'issuer' => array_values(array_filter([
                'required',
                'string',
                'max:500',
                $isSaml ? null : 'url',
            ])),
            'client_id' => [
                Rule::requiredIf(!$isSaml),
                'nullable',
                'string',
                'max:255',
            ],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'idp_sso_url' => [
                Rule::requiredIf($isSaml),
                'nullable',
                'string',
                'max:500',
                'url',
            ],
            'idp_x509_cert' => ['nullable', 'string', 'max:10000'],
            'allowed_email_domains' => ['required', 'string', 'max:2000'],
            'is_enabled' => ['boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Organization;
use App\Support\Translations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteOrganizationRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null
            && ($this->user()?->can('delete', $organization) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
            'confirmation' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $organization = $this->organization();

            if ($organization === null) {
                $validator->errors()->add(
                    'confirmation',
                    Translations::get('settings.delete_organization.no_organization'),
                );

                return;
            }

            if (trim((string) $this->input('confirmation')) !== $organization->name) {
                $validator->errors()->add(
                    'confirmation',
                    Translations::get('settings.delete_organization.confirmation_mismatch'),
                );
            }
        });
    }

    public function organization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

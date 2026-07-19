<?php

namespace App\Http\Requests\Settings;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null
            && ($this->user()?->can('update', $organization) ?? false)
            && !($this->user()?->isPlatformAdmin() ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Organization::LOCALES)],
        ];
    }

    public function organization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

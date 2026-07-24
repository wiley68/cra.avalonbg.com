<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreAzureDevOpsIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $user?->currentOrganization();

        return $user !== null
            && $organization !== null
            && $user->canManageProducts($organization);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'pat' => ['required', 'string', 'min:8', 'max:255'],
            'base_url' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}

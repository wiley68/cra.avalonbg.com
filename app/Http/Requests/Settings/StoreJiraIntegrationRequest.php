<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreJiraIntegrationRequest extends FormRequest
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
            'base_url' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'api_token' => ['required', 'string', 'min:8', 'max:255'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}

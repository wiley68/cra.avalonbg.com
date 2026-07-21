<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreGithubAppVcsConnectionRequest extends FormRequest
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
            'github_app_id' => ['required', 'string', 'max:64'],
            'github_installation_id' => ['required', 'string', 'max:64'],
            'github_private_key' => ['nullable', 'string', 'max:16384'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}

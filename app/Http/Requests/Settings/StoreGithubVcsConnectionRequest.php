<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreGithubVcsConnectionRequest extends FormRequest
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
            'token' => ['required', 'string', 'min:8', 'max:255'],
            'label' => ['nullable', 'string', 'max:120'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', [$user, $organization]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = (int) $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role_id' => ['required', 'exists:roles,id'],
            'must_change_password' => ['nullable', 'boolean'],
            'is_system_admin' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionSlug;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = Organization::query()->first();

        return (bool) $organization && $this->user()->hasPermission(PermissionSlug::UsersUpdate->value, $organization);
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


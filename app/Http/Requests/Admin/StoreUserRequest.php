<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Enums\PermissionSlug;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $organization = Organization::query()->first();

        return (bool) $organization && $this->user()->hasPermission(PermissionSlug::UsersCreate->value, $organization);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => $this->passwordRules(),
            'role_id' => ['required', 'exists:roles,id'],
            'must_change_password' => ['nullable', 'boolean'],
        ];
    }
}


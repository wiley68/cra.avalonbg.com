<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();

        return $organization !== null
            && ($this->user()?->can('create', [User::class, $organization]) ?? false);
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

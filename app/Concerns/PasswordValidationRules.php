<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', $this->passwordRule(), 'confirmed', 'different:current_password'];
    }

    /**
     * Get the validation rules used to validate optional passwords.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function optionalPasswordRules(): array
    {
        return ['nullable', 'string', $this->passwordRule(), 'confirmed'];
    }

    protected function passwordRule(): Password
    {
        return Password::min(9)
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}

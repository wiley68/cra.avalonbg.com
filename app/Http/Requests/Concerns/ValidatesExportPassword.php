<?php

namespace App\Http\Requests\Concerns;

use App\Support\Translations;

trait ValidatesExportPassword
{
    /**
     * @return array<string, list<string>>
     */
    protected function exportPasswordRules(): array
    {
        $minLength = max(1, (int) config('exports.password_min_length', 8));

        return [
            'password' => ['required', 'string', 'min:' . $minLength, 'max:128'],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function exportPasswordMessages(): array
    {
        $minLength = max(1, (int) config('exports.password_min_length', 8));

        return [
            'password.required' => Translations::get('users.export.validation.password_required'),
            'password.min' => Translations::get('users.export.validation.password_min', [
                'min' => (string) $minLength,
            ]),
            'password.max' => Translations::get('users.export.validation.password_max'),
            'password_confirmation.required' => Translations::get('users.export.validation.password_confirmation_required'),
            'password_confirmation.same' => Translations::get('users.export.validation.password_confirmation_same'),
        ];
    }
}

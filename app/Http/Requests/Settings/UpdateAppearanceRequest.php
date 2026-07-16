<?php

namespace App\Http\Requests\Settings;

use App\Enums\Appearance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'appearance' => ['required', 'string', Rule::enum(Appearance::class)],
        ];
    }
}

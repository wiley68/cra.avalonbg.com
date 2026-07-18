<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesExportPassword;
use Illuminate\Foundation\Http\FormRequest;

class ExportPasswordRequest extends FormRequest
{
    use ValidatesExportPassword;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->exportPasswordRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->exportPasswordMessages();
    }
}

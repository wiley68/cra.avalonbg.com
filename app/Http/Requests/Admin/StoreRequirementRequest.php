<?php

namespace App\Http\Requests\Admin;

use App\Models\Requirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Requirement::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'regulation_id' => ['required', 'integer', 'exists:regulations,id'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('requirements', 'code')->where(
                    fn ($query) => $query->where('regulation_id', $this->integer('regulation_id')),
                ),
            ],
            'article_ref' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'requirement_text' => ['required', 'string'],
            'plain_language' => ['nullable', 'string'],
            'applicability_notes' => ['nullable', 'string'],
            'suggested_controls_text' => ['nullable', 'string'],
            'required_evidence_text' => ['nullable', 'string'],
        ];
    }
}

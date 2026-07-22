<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\UserSecurityInstruction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserSecurityInstructionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [UserSecurityInstruction::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $requiresManualFields = !$this->boolean('use_template');

        return [
            'title' => [
                Rule::requiredIf($requiresManualFields),
                'nullable',
                'string',
                'max:255',
            ],
            'version_label' => [
                Rule::requiredIf($requiresManualFields),
                'nullable',
                'string',
                'max:40',
            ],
            'locale' => ['required', 'string', Rule::in(Organization::LOCALES)],
            'notes' => ['nullable', 'string'],
            'use_template' => ['sometimes', 'boolean'],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $this->route('product')?->id),
                ),
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\UserSecurityInstructionSectionKey;
use App\Models\Organization;
use App\Models\UserSecurityInstruction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSecurityInstructionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var UserSecurityInstruction|null $instruction */
        $instruction = $this->route('instruction');

        return $organization !== null
            && $instruction instanceof UserSecurityInstruction
            && $this->user()?->can('update', [$instruction, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'version_label' => ['required', 'string', 'max:40'],
            'locale' => ['required', 'string', Rule::in(Organization::LOCALES)],
            'notes' => ['nullable', 'string'],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $this->route('product')?->id),
                ),
            ],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.section_key' => ['required', 'string', Rule::enum(UserSecurityInstructionSectionKey::class)],
            'sections.*.body' => ['nullable', 'string'],
            'sections.*.title_override' => ['nullable', 'string', 'max:255'],
            'sections.*.is_applicable' => ['sometimes', 'boolean'],
            'sections.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

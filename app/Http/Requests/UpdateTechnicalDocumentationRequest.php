<?php

namespace App\Http\Requests;

use App\Enums\SdlRunStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\TechnicalDocumentationPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTechnicalDocumentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var TechnicalDocumentationPackage|null $package */
        $package = $this->route('package');

        return $organization !== null
            && $package instanceof TechnicalDocumentationPackage
            && $this->user()?->can('update', [$package, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'version_label' => ['required', 'string', 'max:40'],
            'locale' => ['required', 'string', Rule::in(Organization::LOCALES)],
            'notes' => ['nullable', 'string'],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $productId),
                ),
            ],
            'user_security_instruction_id' => [
                'nullable',
                'integer',
                Rule::exists('user_security_instructions', 'id')->where(
                    fn($query) => $query
                        ->where('product_id', $productId)
                        ->where('status', UserSecurityInstructionStatus::Published->value),
                ),
            ],
            'sdl_run_id' => [
                'nullable',
                'integer',
                Rule::exists('sdl_runs', 'id')->where(
                    fn($query) => $query
                        ->where('product_id', $productId)
                        ->where('status', '!=', SdlRunStatus::Cancelled->value),
                ),
            ],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.section_key' => [
                'required',
                'string',
                Rule::enum(TechnicalDocumentationSectionKey::class),
            ],
            'sections.*.body_markdown' => ['nullable', 'string'],
            'sections.*.is_applicable' => ['sometimes', 'boolean'],
            'sections.*.override_reason' => ['nullable', 'string', 'max:1000'],
            'sections.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

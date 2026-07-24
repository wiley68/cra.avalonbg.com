<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\TechnicalDocumentationPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicalDocumentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [TechnicalDocumentationPackage::class, $organization]);
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
            'inherit_from_previous' => ['sometimes', 'boolean'],
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

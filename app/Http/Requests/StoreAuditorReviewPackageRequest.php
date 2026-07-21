<?php

namespace App\Http\Requests;

use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditorReviewPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [AuditorReviewPackage::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'evidence_ids' => ['nullable', 'array'],
            'evidence_ids.*' => ['integer', 'exists:evidence,id'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

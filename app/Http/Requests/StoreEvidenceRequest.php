<?php

namespace App\Http\Requests;

use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [\App\Models\Evidence::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        $memberRule = Rule::exists('organization_user', 'user_id')
            ->where(fn($query) => $query->where('organization_id', $organization?->id));

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(EvidenceType::class)],
            'source' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', $memberRule],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'confidentiality' => ['required', Rule::enum(EvidenceConfidentiality::class)],
            'collected_at' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'review_due_at' => ['nullable', 'date'],
            'freshness_status' => ['required', Rule::enum(EvidenceFreshnessStatus::class)],
            'supersedes_evidence_id' => [
                'nullable',
                'integer',
                Rule::exists('evidence', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'notes' => ['nullable', 'string'],
            'review_notes' => ['nullable', 'string'],
            'reviewer_user_id' => ['nullable', 'integer', $memberRule],
            'reviewed_at' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:20480'],
            'requirement_ids' => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
            'control_ids' => ['nullable', 'array'],
            'control_ids.*' => [
                'integer',
                Rule::exists('controls', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'risk_ids' => ['nullable', 'array'],
            'risk_ids.*' => [
                'integer',
                Rule::exists('product_risks', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'vulnerability_ids' => ['nullable', 'array'],
            'vulnerability_ids.*' => [
                'integer',
                Rule::exists('product_vulnerabilities', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

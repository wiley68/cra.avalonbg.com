<?php

namespace App\Http\Requests;

use App\Enums\ProductRiskStatus;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [\App\Models\ProductRisk::class, $organization]);
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
            'asset' => ['nullable', 'string'],
            'threat' => ['nullable', 'string'],
            'weakness' => ['nullable', 'string'],
            'attack_scenario' => ['nullable', 'string'],
            'category' => ['required', Rule::enum(RiskCategory::class)],
            'likelihood' => ['required', Rule::enum(RiskLikelihood::class)],
            'impact' => ['required', Rule::enum(RiskImpact::class)],
            'residual_likelihood' => ['nullable', Rule::enum(RiskLikelihood::class)],
            'residual_impact' => ['nullable', Rule::enum(RiskImpact::class)],
            'treatment' => ['required', Rule::enum(RiskTreatment::class)],
            'treatment_plan' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProductRiskStatus::class)],
            'owner_user_id' => ['nullable', 'integer', $memberRule],
            'deadline' => ['nullable', 'date'],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'control_ids' => ['nullable', 'array'],
            'control_ids.*' => [
                'integer',
                Rule::exists('controls', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'requirement_ids' => ['nullable', 'array'],
            'requirement_ids.*' => ['integer', Rule::exists('requirements', 'id')],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

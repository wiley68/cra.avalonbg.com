<?php

namespace App\Http\Requests;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var ProductIncident $incident */
        $incident = $this->route('incident');

        return $organization !== null
            && $this->user()?->can('update', [$incident, $organization]);
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
            'status' => ['required', Rule::enum(IncidentStatus::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'summary' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'corrective_measures' => ['nullable', 'string'],
            'lessons_learned' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'integer', $memberRule],
            'actual_started_at' => ['nullable', 'date'],
            'detected_at' => ['nullable', 'date'],
            'awareness_at' => ['nullable', 'date'],
            'classified_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'version_ids' => ['nullable', 'array'],
            'version_ids.*' => [
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => [
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'deployment_ids' => ['nullable', 'array'],
            'deployment_ids.*' => [
                'integer',
                Rule::exists('product_deployments', 'id')->where(
                    fn($query) => $query
                        ->where('product_id', $product->id)
                        ->where('organization_id', $organization?->id),
                ),
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [ProductIncident::class, $organization]);
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
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

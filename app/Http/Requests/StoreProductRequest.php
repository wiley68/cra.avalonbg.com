<?php

namespace App\Http\Requests;

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();

        return $organization !== null
            && ($this->user()?->can('create', [Product::class, $organization]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('slug')),
            ]);
        } elseif ($this->filled('name')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('name')),
            ]);
        }

        $this->merge([
            'has_remote_data_processing' => $this->boolean('has_remote_data_processing'),
            'has_network_connectivity' => $this->boolean('has_network_connectivity'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->user()?->currentOrganization();

        return $this->productRules($organization);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productRules(?Organization $organization, ?Product $product = null): array
    {
        $memberRule = Rule::exists('organization_user', 'user_id')
            ->where(fn($query) => $query->where('organization_id', $organization?->id));

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('products', 'slug')
                    ->where(fn($query) => $query->where('organization_id', $organization?->id))
                    ->ignore($product?->id),
            ],
            'product_line' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'intended_purpose' => ['nullable', 'string'],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'trademark' => ['nullable', 'string', 'max:255'],
            'licensing_model' => ['required', Rule::enum(LicensingModel::class)],
            'has_remote_data_processing' => ['boolean'],
            'has_network_connectivity' => ['boolean'],
            'deployment_model' => ['nullable', 'string', 'max:255'],
            'support_period_notes' => ['nullable', 'string'],
            'end_of_support_policy' => ['nullable', 'string'],
            'product_owner_user_id' => ['nullable', 'integer', $memberRule],
            'security_contact_user_id' => ['nullable', 'integer', $memberRule],
            'scope_status' => ['required', Rule::enum(ScopeStatus::class)],
            'scope_rationale' => ['nullable', 'string'],
            'classification_status' => ['required', Rule::enum(ClassificationStatus::class)],
            'classification_rationale' => ['nullable', 'string'],
            'classification_next_review_at' => ['nullable', 'date'],
        ];
    }
}

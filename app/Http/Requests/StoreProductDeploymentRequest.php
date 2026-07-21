<?php

namespace App\Http\Requests;

use App\Enums\DeploymentEnvironment;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        return $organization !== null
            && $product->organization_id === $organization->id
            && ($this->user()?->can('update', [$product, $organization]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        $organization = $this->user()?->currentOrganization();

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization?->id),
                ),
            ],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'environment' => ['required', Rule::enum(DeploymentEnvironment::class)],
            'installation_date' => ['nullable', 'date'],
            'internet_exposure' => ['sometimes', 'boolean'],
            'update_channel' => ['nullable', 'string', 'max:120'],
            'last_confirmed_at' => ['nullable', 'date'],
            'custom_modifications' => ['sometimes', 'boolean'],
            'end_of_support_exception' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

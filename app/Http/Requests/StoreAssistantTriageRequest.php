<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVulnerability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistantTriageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        return $organization !== null
            && $product->organization_id === $organization->id
            && ($this->user()?->can('view', [$product, $organization]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'vulnerability_id' => [
                'required',
                'integer',
                Rule::exists('product_vulnerabilities', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function vulnerability(): ProductVulnerability
    {
        return ProductVulnerability::query()->findOrFail((int) $this->validated('vulnerability_id'));
    }
}

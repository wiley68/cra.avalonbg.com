<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatchCampaignRequest extends FormRequest
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

        return [
            'title' => ['required', 'string', 'max:255'],
            'target_version_id' => [
                'required',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'product_vulnerability_id' => [
                'nullable',
                'integer',
                Rule::exists('product_vulnerabilities', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'notes' => ['nullable', 'string'],
            'activate' => ['sometimes', 'boolean'],
        ];
    }
}

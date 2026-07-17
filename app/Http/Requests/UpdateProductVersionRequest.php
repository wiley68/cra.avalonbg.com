<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateProductVersionRequest extends StoreProductVersionRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');
        /** @var ProductVersion $version */
        $version = $this->route('version');

        return $organization !== null
            && $product->organization_id === $organization->id
            && $version->product_id === $product->id
            && ($this->user()?->can('update', [$product, $organization]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        /** @var ProductVersion $version */
        $version = $this->route('version');

        return $this->versionRules($product, $version);
    }
}

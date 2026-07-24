<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductSarifUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        return $organization !== null
            && $product->organization_id === $organization->id
            && $this->user()?->can('update', [$product, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimetypes:application/json,text/plain,application/octet-stream',
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

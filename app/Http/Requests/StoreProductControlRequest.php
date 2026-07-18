<?php

namespace App\Http\Requests;

use App\Enums\ProductControlStatus;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [\App\Models\ProductControl::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'control_id' => [
                'required',
                'integer',
                Rule::exists('controls', 'id')->where(
                    fn($query) => $query
                        ->where('organization_id', $organization?->id)
                        ->where('is_active', true),
                ),
                Rule::unique('product_controls', 'control_id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'status' => ['required', Rule::enum(ProductControlStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

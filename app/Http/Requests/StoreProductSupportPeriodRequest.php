<?php

namespace App\Http\Requests;

use App\Enums\SupportPeriodStartBasis;
use App\Enums\SupportPeriodType;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductSupportPeriodRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'type' => ['required', Rule::enum(SupportPeriodType::class)],
            'start_basis' => ['required', Rule::enum(SupportPeriodStartBasis::class)],
            'duration_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'basis' => ['nullable', 'string'],
            'is_extended' => ['nullable', 'boolean'],
            'exceptions_notes' => ['nullable', 'string'],
            'version_ids' => ['nullable', 'array'],
            'version_ids.*' => [
                'integer',
                Rule::exists('product_versions', 'id')
                    ->where(fn($query) => $query->where('product_id', $product->id)),
            ],
        ];
    }
}

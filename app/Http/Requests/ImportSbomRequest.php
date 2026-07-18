<?php

namespace App\Http\Requests;

use App\Enums\SbomFormat;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportSbomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();

        return $organization !== null
            && $this->user()?->can('create', [\App\Models\ProductComponent::class, $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'product_version_id' => [
                'required',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'format' => ['nullable', Rule::enum(SbomFormat::class)],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}

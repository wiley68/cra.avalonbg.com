<?php

namespace App\Http\Requests;

use App\Enums\ComponentSupportStatus;
use App\Enums\PackageEcosystem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var ProductComponent $component */
        $component = $this->route('component');

        return $organization !== null
            && $this->user()?->can('update', [$component, $organization]);
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
            'name' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'package_ecosystem' => ['required', Rule::enum(PackageEcosystem::class)],
            'version' => ['nullable', 'string', 'max:255'],
            'licence' => ['nullable', 'string', 'max:255'],
            'purl' => ['nullable', 'string', 'max:512'],
            'hash' => ['nullable', 'string', 'max:512'],
            'is_direct' => ['sometimes', 'boolean'],
            'is_dev' => ['sometimes', 'boolean'],
            'usage_context' => ['nullable', 'string', 'max:255'],
            'support_status' => ['required', Rule::enum(ComponentSupportStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\ProductVersionState;
use App\Enums\SupportStatus;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVersionRequest extends FormRequest
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

        return $this->versionRules($product);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function versionRules(Product $product, ?ProductVersion $version = null): array
    {
        return [
            'version_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_versions', 'version_number')
                    ->where(fn ($query) => $query->where('product_id', $product->id))
                    ->ignore($version?->id),
            ],
            'release_date' => ['nullable', 'date'],
            'state' => ['required', Rule::enum(ProductVersionState::class)],
            'support_status' => ['required', Rule::enum(SupportStatus::class)],
            'security_support_deadline' => ['nullable', 'date'],
            'git_ref' => ['nullable', 'string', 'max:255'],
            'build_identifier' => ['nullable', 'string', 'max:255'],
            'artifact_hash' => ['nullable', 'string', 'max:255'],
            'changelog' => ['nullable', 'string'],
            'previous_version_id' => array_values(array_filter([
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')
                    ->where(fn ($query) => $query->where('product_id', $product->id)),
                $version !== null ? Rule::notIn([$version->id]) : null,
            ])),
        ];
    }
}

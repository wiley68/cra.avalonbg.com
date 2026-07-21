<?php

namespace App\Http\Requests;

use App\Models\PatchCampaign;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatchCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');
        /** @var PatchCampaign $campaign */
        $campaign = $this->route('campaign');

        return $organization !== null
            && $product->organization_id === $organization->id
            && $campaign instanceof PatchCampaign
            && $campaign->product_id === $product->id
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
        ];
    }
}

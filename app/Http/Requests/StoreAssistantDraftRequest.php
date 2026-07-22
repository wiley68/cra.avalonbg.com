<?php

namespace App\Http\Requests;

use App\Enums\AiDraftType;
use App\Models\PatchCampaign;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistantDraftRequest extends FormRequest
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
            'campaign_id' => [
                'required',
                'integer',
                Rule::exists('patch_campaigns', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'draft_type' => ['required', 'string', Rule::enum(AiDraftType::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function campaign(): PatchCampaign
    {
        return PatchCampaign::query()->findOrFail((int) $this->validated('campaign_id'));
    }

    public function draftType(): AiDraftType
    {
        return AiDraftType::from((string) $this->validated('draft_type'));
    }
}

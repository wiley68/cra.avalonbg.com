<?php

namespace App\Http\Requests;

use App\Enums\PatchCampaignTargetStatus;
use App\Models\PatchCampaign;
use App\Models\PatchCampaignTarget;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatchCampaignTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product $product */
        $product = $this->route('product');
        /** @var PatchCampaign $campaign */
        $campaign = $this->route('campaign');
        /** @var PatchCampaignTarget $target */
        $target = $this->route('target');

        return $organization !== null
            && $product->organization_id === $organization->id
            && $campaign instanceof PatchCampaign
            && $campaign->product_id === $product->id
            && $target instanceof PatchCampaignTarget
            && $target->campaign_id === $campaign->id
            && ($this->user()?->can('update', [$product, $organization]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    PatchCampaignTargetStatus::Notified->value,
                    PatchCampaignTargetStatus::Acknowledged->value,
                    PatchCampaignTargetStatus::Updated->value,
                    PatchCampaignTargetStatus::Excepted->value,
                ]),
            ],
            'notification_note' => ['nullable', 'string'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\SdlStageStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SdlRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSdlStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->currentOrganization();
        /** @var SdlRun|null $run */
        $run = $this->route('sdlRun');

        return $organization !== null
            && $run instanceof SdlRun
            && $this->user()?->can('update', [$run, $organization]);
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
            'status' => ['required', Rule::enum(SdlStageStatus::class)],
            'notes' => ['nullable', 'string'],
            'evidence_ids' => ['nullable', 'array'],
            'evidence_ids.*' => [
                'integer',
                Rule::exists('evidence', 'id')->where(
                    fn($query) => $query
                        ->where('organization_id', $organization?->id)
                        ->where('product_id', $product->id),
                ),
            ],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

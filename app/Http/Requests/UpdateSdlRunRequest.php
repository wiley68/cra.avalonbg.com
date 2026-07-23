<?php

namespace App\Http\Requests;

use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SdlRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSdlRunRequest extends FormRequest
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

        $memberRule = Rule::exists('organization_user', 'user_id')
            ->where(fn($query) => $query->where('organization_id', $organization?->id));

        return [
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(SdlRunStatus::class)],
            'current_stage' => ['required', Rule::enum(SdlStage::class)],
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'owner_user_id' => ['nullable', 'integer', $memberRule],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function currentOrganization(): ?Organization
    {
        return $this->user()?->currentOrganization();
    }
}

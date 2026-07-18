<?php

namespace App\Http\Requests;

use App\Enums\RequirementApplicabilityStatus;
use App\Models\Product;
use App\Models\ProductRequirement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var ProductRequirement|null $requirement */
        $requirement = $this->route('requirement');

        if ($organization === null || $requirement === null) {
            return false;
        }

        return $this->user()?->can('update', [$requirement, $organization]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product|null $product */
        $product = $this->route('product');

        $memberRule = Rule::exists('organization_user', 'user_id')
            ->where(fn ($query) => $query->where('organization_id', $organization?->id ?? $product?->organization_id));

        return [
            'status' => ['required', Rule::enum(RequirementApplicabilityStatus::class)],
            'rationale' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'integer', $memberRule],
        ];
    }
}

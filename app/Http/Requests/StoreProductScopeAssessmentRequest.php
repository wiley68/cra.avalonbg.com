<?php

namespace App\Http\Requests;

use App\Enums\ScopeStatus;
use App\Models\Product;
use App\Support\ScopeAssessmentValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductScopeAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        /** @var Product|null $product */
        $product = $this->route('product');

        if ($organization === null || $product === null) {
            return false;
        }

        return $this->user()?->can('update', [$product, $organization]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...ScopeAssessmentValidation::answerRules(),
            'final_status' => ['required', Rule::enum(ScopeStatus::class)],
            'rationale' => ['nullable', 'string'],
        ];
    }
}

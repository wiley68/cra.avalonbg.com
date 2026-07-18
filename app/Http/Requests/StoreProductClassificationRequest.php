<?php

namespace App\Http\Requests;

use App\Enums\ClassificationStatus;
use App\Models\Product;
use App\Support\ClassificationAssessmentValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductClassificationRequest extends FormRequest
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
            ...ClassificationAssessmentValidation::answerRules(),
            'final_status' => ['required', Rule::enum(ClassificationStatus::class)],
            'rationale' => ['nullable', 'string'],
            'regulatory_content_version' => ['required', 'string', 'max:255'],
            'evidence_notes' => ['nullable', 'string'],
            'next_review_at' => ['nullable', 'date'],
        ];
    }
}

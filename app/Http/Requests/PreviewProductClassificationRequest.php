<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\ClassificationAssessmentValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewProductClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->user()?->currentOrganization();
        $user = $this->user();

        if ($organization === null || $user === null) {
            return false;
        }

        return $user->can('create', [Product::class, $organization])
            || $user->canManageProducts($organization);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ClassificationAssessmentValidation::answerRules();
    }
}

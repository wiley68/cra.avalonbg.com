<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\ScopeAssessmentValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewProductScopeAssessmentRequest extends FormRequest
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
        return ScopeAssessmentValidation::answerRules();
    }
}

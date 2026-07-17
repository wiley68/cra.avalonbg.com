<?php

namespace App\Support;

use App\Enums\ProductType;
use App\Enums\ScopeAnswerTriState;
use App\Enums\ScopeMarketRole;
use App\Enums\ScopeQuestionKey;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ScopeAssessmentValidation
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function answerRules(string $prefix = 'answers', bool $answersRequired = true): array
    {
        $triState = Rule::enum(ScopeAnswerTriState::class);
        $required = $answersRequired ? 'required' : 'required_with:' . $prefix;

        return [
            $prefix => [$answersRequired ? 'required' : 'nullable', 'array'],
            $prefix . '.' . ScopeQuestionKey::ProductKind->value => [
                $required,
                Rule::enum(ProductType::class),
            ],
            $prefix . '.' . ScopeQuestionKey::CommercialActivity->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::NetworkOrDeviceLink->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::OfferedStandalone->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::SoldUnderOwnBrand->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::RemoteProcessingRequired->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::OtherSectorRegulation->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::ComponentOfOtherProduct->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::FreeOpenSource->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::SubstantialModification->value => [$required, $triState],
            $prefix . '.' . ScopeQuestionKey::MarketRole->value => [
                $required,
                Rule::enum(ScopeMarketRole::class),
            ],
            $prefix . '.' . ScopeQuestionKey::OfferedInEu->value => [$required, $triState],
        ];
    }
}

<?php

namespace App\Support;

use App\Enums\ClassificationQuestionKey;
use App\Enums\ScopeAnswerTriState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ClassificationAssessmentValidation
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function answerRules(string $prefix = 'answers', bool $answersRequired = true): array
    {
        $triState = Rule::enum(ScopeAnswerTriState::class);
        $required = $answersRequired ? 'required' : 'required_with:'.$prefix;

        $rules = [
            $prefix => [$answersRequired ? 'required' : 'nullable', 'array'],
        ];

        foreach (ClassificationQuestionKey::ordered() as $question) {
            $rules[$prefix.'.'.$question->value] = [$required, $triState];
        }

        return $rules;
    }
}

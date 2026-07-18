<?php

namespace App\Services;

use App\Enums\ClassificationQuestionKey;
use App\Enums\ClassificationStatus;
use App\Enums\ScopeAnswerTriState;
use App\Models\Product;
use App\Models\ProductClassification;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassificationAssessmentService
{
    /**
     * @param  array<string, string>  $answers
     * @return array{suggested_status: ClassificationStatus, rationale: string}
     */
    public function evaluate(array $answers): array
    {
        $this->assertCompleteAnswers($answers);

        $status = $this->suggestStatus($answers);
        $rationale = $this->buildRationale($answers, $status);

        return [
            'suggested_status' => $status,
            'rationale' => $rationale,
        ];
    }

    /**
     * @param  array<string, string>  $answers
     */
    public function storeAndApply(
        Product $product,
        array $answers,
        ClassificationStatus $finalStatus,
        ?string $rationale,
        string $regulatoryContentVersion,
        ?string $evidenceNotes,
        ?string $nextReviewAt,
        User $reviewer,
    ): ProductClassification {
        $evaluation = $this->evaluate($answers);

        return DB::transaction(function () use ($product, $answers, $finalStatus, $rationale, $regulatoryContentVersion, $evidenceNotes, $nextReviewAt, $reviewer, $evaluation) {
            $resolvedRationale = $rationale !== null && trim($rationale) !== ''
                ? trim($rationale)
                : $evaluation['rationale'];

            $isApproved = $finalStatus !== ClassificationStatus::UnderReview
                && $finalStatus !== ClassificationStatus::Unclassified;

            $assessment = ProductClassification::query()->create([
                'product_id' => $product->id,
                'answers' => $answers,
                'suggested_status' => $evaluation['suggested_status'],
                'final_status' => $finalStatus,
                'rationale' => $resolvedRationale,
                'regulatory_content_version' => trim($regulatoryContentVersion),
                'evidence_notes' => $evidenceNotes !== null && trim($evidenceNotes) !== ''
                    ? trim($evidenceNotes)
                    : null,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'approved_by' => $isApproved ? $reviewer->id : null,
                'approved_at' => $isApproved ? now() : null,
                'next_review_at' => $nextReviewAt ?: null,
            ]);

            $this->applyToProduct(
                $product,
                $finalStatus,
                $assessment->rationale,
                $nextReviewAt,
                $reviewer,
            );

            return $assessment;
        });
    }

    public function applyToProduct(
        Product $product,
        ClassificationStatus $finalStatus,
        ?string $rationale,
        ?string $nextReviewAt,
        User $reviewer,
    ): void {
        $product->update([
            'classification_status' => $finalStatus,
            'classification_rationale' => $rationale,
            'classification_reviewed_at' => now(),
            'classification_reviewed_by' => $reviewer->id,
            'classification_next_review_at' => $nextReviewAt ?: null,
        ]);
    }

    /**
     * Deterministic first-pass CRA Annex III/IV classification suggestion.
     *
     * @param  array<string, string>  $answers
     */
    public function suggestStatus(array $answers): ClassificationStatus
    {
        $values = [];
        foreach (ClassificationQuestionKey::ordered() as $question) {
            $values[$question->value] = $answers[$question->value] ?? null;
        }

        if (in_array(ScopeAnswerTriState::Unsure->value, $values, true)) {
            return ClassificationStatus::Unclassified;
        }

        if ($values[ClassificationQuestionKey::ExplicitlyExcluded->value] === ScopeAnswerTriState::Yes->value) {
            return ClassificationStatus::Excluded;
        }

        if ($values[ClassificationQuestionKey::SectorSpecificRegime->value] === ScopeAnswerTriState::Yes->value) {
            return ClassificationStatus::SectorSpecific;
        }

        $critical = $values[ClassificationQuestionKey::CriticalInfrastructure->value] === ScopeAnswerTriState::Yes->value
            || $values[ClassificationQuestionKey::PkiCrypto->value] === ScopeAnswerTriState::Yes->value;

        $classIi = $values[ClassificationQuestionKey::OperatingSystem->value] === ScopeAnswerTriState::Yes->value
            || $values[ClassificationQuestionKey::HypervisorContainers->value] === ScopeAnswerTriState::Yes->value;

        $classI = $values[ClassificationQuestionKey::IdentityAccessSecurity->value] === ScopeAnswerTriState::Yes->value
            || $values[ClassificationQuestionKey::NetworkSecurity->value] === ScopeAnswerTriState::Yes->value
            || $values[ClassificationQuestionKey::EndpointSecurity->value] === ScopeAnswerTriState::Yes->value
            || $values[ClassificationQuestionKey::BrowserOrRuntime->value] === ScopeAnswerTriState::Yes->value;

        // Critical + Class II signals without clear hierarchy → further review
        if ($critical && $classIi) {
            return ClassificationStatus::UnderReview;
        }

        if ($critical) {
            return ClassificationStatus::Critical;
        }

        if ($classIi) {
            return ClassificationStatus::ImportantClassIi;
        }

        if ($classI) {
            return ClassificationStatus::ImportantClassI;
        }

        return ClassificationStatus::General;
    }

    /**
     * @param  array<string, string>  $answers
     */
    public function buildRationale(array $answers, ClassificationStatus $status): string
    {
        $parts = [
            Translations::get('products.classification_wizard.rationale_prefix', [
                'status' => Translations::get('products.classification.'.$status->value),
            ]),
        ];

        foreach (ClassificationQuestionKey::ordered() as $question) {
            $value = $answers[$question->value] ?? null;
            if ($value === null) {
                continue;
            }

            $label = Translations::get('products.classification_wizard.questions.'.$question->value.'.label');
            $answerLabel = Translations::get('products.scope_wizard.tri_state.'.$value);
            $parts[] = "{$label}: {$answerLabel}.";
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestPayload(?ProductClassification $assessment): ?array
    {
        if ($assessment === null) {
            return null;
        }

        return [
            'id' => $assessment->id,
            'answers' => $assessment->answers,
            'suggested_status' => $assessment->suggested_status->value,
            'final_status' => $assessment->final_status->value,
            'rationale' => $assessment->rationale,
            'regulatory_content_version' => $assessment->regulatory_content_version,
            'evidence_notes' => $assessment->evidence_notes,
            'reviewed_at' => $assessment->reviewed_at?->toIso8601String(),
            'reviewed_by' => $assessment->reviewed_by,
            'approved_at' => $assessment->approved_at?->toIso8601String(),
            'approved_by' => $assessment->approved_by,
            'next_review_at' => $assessment->next_review_at?->toDateString(),
        ];
    }

    /**
     * @param  array<string, string>  $answers
     */
    private function assertCompleteAnswers(array $answers): void
    {
        $missing = [];

        foreach (ClassificationQuestionKey::ordered() as $question) {
            if (! array_key_exists($question->value, $answers) || $answers[$question->value] === '') {
                $missing[$question->value] = Translations::get(
                    'products.classification_wizard.errors.answer_required',
                );
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }
}

<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Enums\ScopeAnswerTriState;
use App\Enums\ScopeMarketRole;
use App\Enums\ScopeQuestionKey;
use App\Enums\ScopeStatus;
use App\Models\Product;
use App\Models\ProductScopeAssessment;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScopeAssessmentService
{
    /**
     * @param  array<string, string>  $answers
     * @return array{suggested_status: ScopeStatus, rationale: string}
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
        ScopeStatus $finalStatus,
        ?string $rationale,
        User $reviewer,
    ): ProductScopeAssessment {
        $evaluation = $this->evaluate($answers);

        return DB::transaction(function () use ($product, $answers, $finalStatus, $rationale, $reviewer, $evaluation) {
            $assessment = ProductScopeAssessment::query()->create([
                'product_id' => $product->id,
                'answers' => $answers,
                'suggested_status' => $evaluation['suggested_status'],
                'final_status' => $finalStatus,
                'rationale' => $rationale !== null && trim($rationale) !== ''
                    ? trim($rationale)
                    : $evaluation['rationale'],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->applyToProduct($product, $answers, $finalStatus, $assessment->rationale, $reviewer);

            return $assessment;
        });
    }

    /**
     * @param  array<string, string>  $answers
     */
    public function applyToProduct(
        Product $product,
        array $answers,
        ScopeStatus $finalStatus,
        ?string $rationale,
        User $reviewer,
    ): void {
        $productKind = $answers[ScopeQuestionKey::ProductKind->value] ?? null;

        $productType = match ($productKind) {
            ProductType::Software->value => ProductType::Software,
            ProductType::Hardware->value => ProductType::Hardware,
            default => ProductType::Other,
        };

        $product->update([
            'product_type' => $productType,
            'has_network_connectivity' => ($answers[ScopeQuestionKey::NetworkOrDeviceLink->value] ?? null)
                === ScopeAnswerTriState::Yes->value,
            'has_remote_data_processing' => ($answers[ScopeQuestionKey::RemoteProcessingRequired->value] ?? null)
                === ScopeAnswerTriState::Yes->value,
            'scope_status' => $finalStatus,
            'scope_rationale' => $rationale,
            'scope_reviewed_at' => now(),
            'scope_reviewed_by' => $reviewer->id,
        ]);
    }

    /**
     * Deterministic first-pass CRA scope suggestion.
     *
     * @param  array<string, string>  $answers
     */
    public function suggestStatus(array $answers): ScopeStatus
    {
        $commercial = $answers[ScopeQuestionKey::CommercialActivity->value] ?? null;
        $eu = $answers[ScopeQuestionKey::OfferedInEu->value] ?? null;
        $network = $answers[ScopeQuestionKey::NetworkOrDeviceLink->value] ?? null;
        $remote = $answers[ScopeQuestionKey::RemoteProcessingRequired->value] ?? null;
        $role = $answers[ScopeQuestionKey::MarketRole->value] ?? null;
        $otherSector = $answers[ScopeQuestionKey::OtherSectorRegulation->value] ?? null;
        $component = $answers[ScopeQuestionKey::ComponentOfOtherProduct->value] ?? null;
        $foss = $answers[ScopeQuestionKey::FreeOpenSource->value] ?? null;
        $modification = $answers[ScopeQuestionKey::SubstantialModification->value] ?? null;
        $standalone = $answers[ScopeQuestionKey::OfferedStandalone->value] ?? null;
        $ownBrand = $answers[ScopeQuestionKey::SoldUnderOwnBrand->value] ?? null;
        $kind = $answers[ScopeQuestionKey::ProductKind->value] ?? null;

        $unsureKeys = [
            $commercial,
            $eu,
            $network,
            $remote,
            $otherSector,
            $component,
            $foss,
            $modification,
            $standalone,
            $ownBrand,
        ];

        if (
            in_array(ScopeAnswerTriState::Unsure->value, $unsureKeys, true)
            || $role === ScopeMarketRole::Unsure->value
            || $kind === null
        ) {
            return ScopeStatus::InsufficientInformation;
        }

        if ($commercial === ScopeAnswerTriState::No->value) {
            return ScopeStatus::OutOfScope;
        }

        if ($eu === ScopeAnswerTriState::No->value) {
            return ScopeStatus::PotentiallyExcluded;
        }

        if ($otherSector === ScopeAnswerTriState::Yes->value) {
            return ScopeStatus::FurtherLegalReview;
        }

        if (
            $foss === ScopeAnswerTriState::Yes->value
            && $commercial === ScopeAnswerTriState::No->value
        ) {
            return ScopeStatus::PotentiallyExcluded;
        }

        if (
            $component === ScopeAnswerTriState::Yes->value
            && $standalone === ScopeAnswerTriState::No->value
        ) {
            return ScopeStatus::FurtherLegalReview;
        }

        if ($modification === ScopeAnswerTriState::Yes->value) {
            return ScopeStatus::FurtherLegalReview;
        }

        $hasConnectivity = $network === ScopeAnswerTriState::Yes->value
            || $remote === ScopeAnswerTriState::Yes->value;

        $isEconomicOperator = in_array($role, [
            ScopeMarketRole::Manufacturer->value,
            ScopeMarketRole::Importer->value,
            ScopeMarketRole::Distributor->value,
        ], true);

        if (
            $commercial === ScopeAnswerTriState::Yes->value
            && $eu === ScopeAnswerTriState::Yes->value
            && $hasConnectivity
            && $isEconomicOperator
            && in_array($kind, [ProductType::Software->value, ProductType::Hardware->value], true)
        ) {
            return ScopeStatus::LikelyInScope;
        }

        if (! $hasConnectivity && $kind === ProductType::Other->value) {
            return ScopeStatus::PotentiallyExcluded;
        }

        return ScopeStatus::FurtherLegalReview;
    }

    /**
     * @param  array<string, string>  $answers
     */
    public function buildRationale(array $answers, ScopeStatus $status): string
    {
        $parts = [
            Translations::get('products.scope_wizard.rationale_prefix', [
                'status' => Translations::get('products.scope.'.$status->value),
            ]),
        ];

        foreach (ScopeQuestionKey::ordered() as $question) {
            $value = $answers[$question->value] ?? null;
            if ($value === null) {
                continue;
            }

            $label = Translations::get('products.scope_wizard.questions.'.$question->value.'.label');
            $answerLabel = $this->answerLabel($question, $value);
            $parts[] = "{$label}: {$answerLabel}.";
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestPayload(?ProductScopeAssessment $assessment): ?array
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
            'reviewed_at' => $assessment->reviewed_at?->toIso8601String(),
            'reviewed_by' => $assessment->reviewed_by,
        ];
    }

    /**
     * @param  array<string, string>  $answers
     */
    private function assertCompleteAnswers(array $answers): void
    {
        $missing = [];

        foreach (ScopeQuestionKey::ordered() as $question) {
            if (! array_key_exists($question->value, $answers) || $answers[$question->value] === '') {
                $missing[$question->value] = Translations::get('products.scope_wizard.errors.answer_required');
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    private function answerLabel(ScopeQuestionKey $question, string $value): string
    {
        if ($question === ScopeQuestionKey::ProductKind) {
            return Translations::get('products.types.'.$value);
        }

        if ($question === ScopeQuestionKey::MarketRole) {
            return Translations::get('products.scope_wizard.market_roles.'.$value);
        }

        return Translations::get('products.scope_wizard.tri_state.'.$value);
    }
}

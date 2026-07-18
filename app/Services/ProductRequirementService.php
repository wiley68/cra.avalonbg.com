<?php

namespace App\Services;

use App\Enums\RequirementApplicabilityStatus;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Models\ProductRequirementHistory;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductRequirementService
{
    /**
     * Ensure every active requirement with a current version has a product_requirements row.
     */
    public function ensureMatrix(Product $product): void
    {
        $currentVersions = RequirementVersion::query()
            ->where('is_current', true)
            ->whereHas('requirement', fn ($query) => $query->where('is_active', true))
            ->with('requirement')
            ->get();

        $existing = ProductRequirement::query()
            ->where('product_id', $product->id)
            ->pluck('requirement_id')
            ->all();

        $existingSet = array_flip($existing);

        foreach ($currentVersions as $version) {
            if (isset($existingSet[$version->requirement_id])) {
                continue;
            }

            ProductRequirement::query()->create([
                'product_id' => $product->id,
                'requirement_id' => $version->requirement_id,
                'requirement_version_id' => $version->id,
                'status' => RequirementApplicabilityStatus::NotAssessed,
                'rationale' => null,
                'owner_user_id' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);
        }
    }

    public function updateApplicability(
        ProductRequirement $productRequirement,
        RequirementApplicabilityStatus $status,
        ?string $rationale,
        ?int $ownerUserId,
        User $reviewer,
    ): ProductRequirement {
        return DB::transaction(function () use ($productRequirement, $status, $rationale, $ownerUserId, $reviewer) {
            $fromStatus = $productRequirement->status;

            $currentVersion = RequirementVersion::query()
                ->where('requirement_id', $productRequirement->requirement_id)
                ->where('is_current', true)
                ->first();

            if ($currentVersion !== null) {
                $productRequirement->requirement_version_id = $currentVersion->id;
            }

            $productRequirement->fill([
                'status' => $status,
                'rationale' => $rationale !== null && trim($rationale) !== '' ? trim($rationale) : null,
                'owner_user_id' => $ownerUserId,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
            $productRequirement->save();

            if ($fromStatus !== $status || $productRequirement->wasChanged('rationale')) {
                ProductRequirementHistory::query()->create([
                    'product_requirement_id' => $productRequirement->id,
                    'from_status' => $fromStatus?->value,
                    'to_status' => $status->value,
                    'rationale' => $productRequirement->rationale,
                    'changed_by' => $reviewer->id,
                    'created_at' => now(),
                ]);
            }

            return $productRequirement->fresh([
                'requirement.regulation',
                'requirementVersion',
                'owner',
                'reviewer',
            ]);
        });
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     article_ref: string|null,
     *     regulation_code: string|null,
     *     status: string,
     *     plain_language: string|null,
     *     requirement_text: string|null,
     *     suggested_controls_text: string|null,
     *     required_evidence_text: string|null,
     *     version: int|null,
     *     rationale: string|null,
     *     owner_user_id: int|null,
     *     owner_name: string|null,
     *     reviewed_at: string|null,
     *     reviewed_by: int|null
     * }
     */
    public function listItemPayload(ProductRequirement $row): array
    {
        $version = $row->requirementVersion;
        $requirement = $row->requirement;

        return [
            'id' => $row->id,
            'code' => $requirement?->code ?? '',
            'article_ref' => $requirement?->article_ref,
            'regulation_code' => $requirement?->regulation?->code,
            'status' => $row->status->value,
            'plain_language' => $version?->plain_language,
            'requirement_text' => $version?->requirement_text,
            'suggested_controls_text' => $version?->suggested_controls_text,
            'required_evidence_text' => $version?->required_evidence_text,
            'version' => $version?->version,
            'rationale' => $row->rationale,
            'owner_user_id' => $row->owner_user_id,
            'owner_name' => $row->owner?->name,
            'reviewed_at' => $row->reviewed_at?->toIso8601String(),
            'reviewed_by' => $row->reviewed_by,
        ];
    }
}

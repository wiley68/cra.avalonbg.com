<?php

namespace App\Services;

use App\Enums\RiskLevel;
use App\Models\Control;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductRiskService
{
    public static function levelFromScores(int $likelihood, int $impact): RiskLevel
    {
        $score = $likelihood * $impact;

        return match (true) {
            $score >= 17 => RiskLevel::Critical,
            $score >= 10 => RiskLevel::High,
            $score >= 5 => RiskLevel::Medium,
            default => RiskLevel::Low,
        };
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     title: string,
     *     category: string,
     *     status: string,
     *     treatment: string,
     *     initial_risk: string,
     *     residual_risk: string|null,
     *     owner_name: string|null,
     *     deadline: string|null,
     *     reviewed_at: string|null
     * }>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = ProductRisk::query()
            ->where('product_id', $product->id)
            ->with('owner');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('treatment', 'like', "%{$search}%")
                    ->orWhere('asset', 'like', "%{$search}%")
                    ->orWhere('threat', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'category' => 'category',
            'status' => 'status',
            'treatment' => 'treatment',
            'deadline' => 'deadline',
            'reviewed_at' => 'reviewed_at',
            'initial_risk' => DB::raw('(likelihood * impact)'),
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(ProductRisk $risk) => $this->listItemPayload($risk));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $controlIds
     * @param  list<int>  $requirementIds
     */
    public function create(
        Product $product,
        array $attributes,
        array $controlIds,
        array $requirementIds,
        User $reviewer,
    ): ProductRisk {
        $risk = DB::transaction(function () use ($product, $attributes, $controlIds, $requirementIds, $reviewer) {
            $this->assertControlsBelongToProductOrganization($product, $controlIds);

            /** @var ProductRisk $risk */
            $risk = ProductRisk::query()->create([
                ...$attributes,
                'product_id' => $product->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $risk->controls()->sync($this->uniqueIds($controlIds));
            $risk->requirements()->sync($this->uniqueIds($requirementIds));

            return $risk->load(['owner', 'controls', 'requirements', 'productVersion', 'product']);
        });

        AuditLogger::logRiskCreated($risk, $reviewer);

        return $risk;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $controlIds
     * @param  list<int>  $requirementIds
     */
    public function update(
        ProductRisk $risk,
        array $attributes,
        array $controlIds,
        array $requirementIds,
        User $reviewer,
    ): ProductRisk {
        $risk = DB::transaction(function () use ($risk, $attributes, $controlIds, $requirementIds, $reviewer) {
            $this->assertControlsBelongToProductOrganization($risk->product, $controlIds);

            $risk->update([
                ...$attributes,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $risk->controls()->sync($this->uniqueIds($controlIds));
            $risk->requirements()->sync($this->uniqueIds($requirementIds));

            return $risk->fresh(['owner', 'controls', 'requirements', 'productVersion', 'product']);
        });

        AuditLogger::logRiskUpdated($risk, $reviewer);

        return $risk;
    }

    public function delete(ProductRisk $risk): void
    {
        $risk->loadMissing('product');
        $actor = Auth::user();

        if ($actor instanceof User) {
            AuditLogger::logRiskDeleted($risk, $actor);
        }

        $risk->delete();
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     category: string,
     *     status: string,
     *     treatment: string,
     *     initial_risk: string,
     *     residual_risk: string|null,
     *     owner_name: string|null,
     *     deadline: string|null,
     *     reviewed_at: string|null
     * }
     */
    public function listItemPayload(ProductRisk $risk): array
    {
        return [
            'id' => $risk->id,
            'title' => $risk->title,
            'category' => $risk->category->value,
            'status' => $risk->status->value,
            'treatment' => $risk->treatment->value,
            'initial_risk' => $risk->initialRiskLevel()->value,
            'residual_risk' => $risk->residualRiskLevel()?->value,
            'owner_name' => $risk->owner?->name,
            'deadline' => $risk->deadline?->toDateString(),
            'reviewed_at' => $risk->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ProductRisk $risk): array
    {
        return [
            'id' => $risk->id,
            'title' => $risk->title,
            'asset' => $risk->asset,
            'threat' => $risk->threat,
            'weakness' => $risk->weakness,
            'attack_scenario' => $risk->attack_scenario,
            'category' => $risk->category->value,
            'likelihood' => $risk->likelihood->value,
            'impact' => $risk->impact->value,
            'residual_likelihood' => $risk->residual_likelihood?->value,
            'residual_impact' => $risk->residual_impact?->value,
            'treatment' => $risk->treatment->value,
            'treatment_plan' => $risk->treatment_plan,
            'status' => $risk->status->value,
            'owner_user_id' => $risk->owner_user_id,
            'deadline' => $risk->deadline?->toDateString(),
            'product_version_id' => $risk->product_version_id,
            'initial_risk' => $risk->initialRiskLevel()->value,
            'residual_risk' => $risk->residualRiskLevel()?->value,
            'control_ids' => $risk->controls->pluck('id')->all(),
            'requirement_ids' => $risk->requirements->pluck('id')->all(),
            'reviewed_at' => $risk->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $controlIds
     */
    private function assertControlsBelongToProductOrganization(Product $product, array $controlIds): void
    {
        $ids = $this->uniqueIds($controlIds);

        if ($ids === []) {
            return;
        }

        $validCount = Control::query()
            ->where('organization_id', $product->organization_id)
            ->whereIn('id', $ids)
            ->count();

        if ($validCount !== count($ids)) {
            throw ValidationException::withMessages([
                'control_ids' => 'One or more controls are invalid for this organization.',
            ]);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}

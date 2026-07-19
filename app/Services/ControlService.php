<?php

namespace App\Services;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Enums\ControlSource;
use App\Enums\ProductControlStatus;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\User;
use Database\Seeders\ControlCatalogueSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ControlService
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     automation_level: string,
     *     frequency: string,
     *     is_active: bool,
     *     owner_name: string|null,
     *     requirements_count: int
     * }>
     */
    public function paginate(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'name',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = Control::query()
            ->where('organization_id', $organization->id)
            ->with('owner')
            ->withCount('requirements');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('automation_level', 'like', "%{$search}%")
                    ->orWhere('frequency', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'code' => 'code',
            'automation_level' => 'automation_level',
            'frequency' => 'frequency',
            'is_active' => 'is_active',
            default => 'name',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(Control $control) => $this->listItemPayload($control));
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     control_id: int,
     *     code: string,
     *     name: string,
     *     status: string,
     *     notes: string|null,
     *     reviewed_at: string|null
     * }>
     */
    public function paginateProductControls(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'name',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = ProductControl::query()
            ->where('product_controls.product_id', $product->id)
            ->join('controls', 'controls.id', '=', 'product_controls.control_id')
            ->select('product_controls.*')
            ->with('control');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('controls.name', 'like', "%{$search}%")
                    ->orWhere('controls.code', 'like', "%{$search}%")
                    ->orWhere('product_controls.status', 'like', "%{$search}%")
                    ->orWhere('product_controls.notes', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('product_controls.id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'product_controls.id',
            'code' => 'controls.code',
            'status' => 'product_controls.status',
            'reviewed_at' => 'product_controls.reviewed_at',
            default => 'controls.name',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['product_controls.*'], 'page', $page)
            ->through(fn(ProductControl $row) => $this->productControlListPayload($row));
    }

    /**
     * @param  list<int>  $requirementIds
     */
    public function create(
        Organization $organization,
        array $attributes,
        array $requirementIds = [],
    ): Control {
        return DB::transaction(function () use ($organization, $attributes, $requirementIds) {
            $control = Control::query()->create([
                ...$attributes,
                'organization_id' => $organization->id,
                'source' => $attributes['source'] ?? ControlSource::Custom,
            ]);

            $control->requirements()->sync($this->filterRequirementIds($requirementIds));

            return $control->load(['owner', 'requirements']);
        });
    }

    /**
     * @param  list<int>  $requirementIds
     */
    public function update(Control $control, array $attributes, array $requirementIds): Control
    {
        return DB::transaction(function () use ($control, $attributes, $requirementIds) {
            $control->update($attributes);
            $control->requirements()->sync($this->filterRequirementIds($requirementIds));

            return $control->fresh(['owner', 'requirements']);
        });
    }

    public function delete(Control $control): void
    {
        $control->delete();
    }

    public function assignToProduct(
        Product $product,
        Control $control,
        ProductControlStatus $status,
        ?string $notes,
        User $reviewer,
    ): ProductControl {
        return DB::transaction(function () use ($product, $control, $status, $notes, $reviewer) {
            /** @var ProductControl $productControl */
            $productControl = ProductControl::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'control_id' => $control->id,
                ],
                [
                    'status' => $status,
                    'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ],
            );

            return $productControl->load('control');
        });
    }

    public function updateProductControl(
        ProductControl $productControl,
        ProductControlStatus $status,
        ?string $notes,
        User $reviewer,
    ): ProductControl {
        $productControl->update([
            'status' => $status,
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $productControl->fresh('control');
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function seedStarterCatalogue(Organization $organization, bool $refreshExisting = true): array
    {
        return (new ControlCatalogueSeeder)->seedForOrganization($organization, $refreshExisting);
    }

    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     automation_level: string,
     *     frequency: string,
     *     is_active: bool,
     *     source: string,
     *     owner_name: string|null,
     *     requirements_count: int
     * }
     */
    public function listItemPayload(Control $control): array
    {
        return [
            'id' => $control->id,
            'code' => $control->code,
            'name' => $control->name,
            'automation_level' => $control->automation_level instanceof ControlAutomationLevel
                ? $control->automation_level->value
                : (string) $control->automation_level,
            'frequency' => $control->frequency instanceof ControlFrequency
                ? $control->frequency->value
                : (string) $control->frequency,
            'is_active' => (bool) $control->is_active,
            'source' => $control->source instanceof ControlSource
                ? $control->source->value
                : (string) $control->source,
            'owner_name' => $control->owner?->name,
            'requirements_count' => (int) ($control->requirements_count ?? $control->requirements()->count()),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     control_id: int,
     *     code: string,
     *     name: string,
     *     status: string,
     *     notes: string|null,
     *     reviewed_at: string|null
     * }
     */
    public function productControlListPayload(ProductControl $productControl): array
    {
        $control = $productControl->control;

        return [
            'id' => $productControl->id,
            'control_id' => $productControl->control_id,
            'code' => $control?->code ?? '',
            'name' => $control?->name ?? '',
            'status' => $productControl->status instanceof ProductControlStatus
                ? $productControl->status->value
                : (string) $productControl->status,
            'notes' => $productControl->notes,
            'reviewed_at' => $productControl->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $requirementIds
     * @return list<int>
     */
    private function filterRequirementIds(array $requirementIds): array
    {
        return array_values(array_unique(array_map('intval', $requirementIds)));
    }
}

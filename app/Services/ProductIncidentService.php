<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductIncidentService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = ProductIncident::query()
            ->where('product_id', $product->id)
            ->with('owner');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('severity', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'status' => 'status',
            'severity' => 'severity',
            'awareness_at' => 'awareness_at',
            'detected_at' => 'detected_at',
            'classified_at' => 'classified_at',
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(ProductIncident $incident) => $this->listItemPayload($incident));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $versionIds
     */
    public function create(Product $product, array $attributes, array $versionIds): ProductIncident
    {
        return DB::transaction(function () use ($product, $attributes, $versionIds) {
            $this->assertVersionsBelongToProduct($product, $versionIds);

            /** @var ProductIncident $incident */
            $incident = ProductIncident::query()->create([
                ...$attributes,
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
            ]);

            $incident->versions()->sync($versionIds);

            return $incident->load(['owner', 'versions']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $versionIds
     */
    public function update(ProductIncident $incident, array $attributes, array $versionIds): ProductIncident
    {
        return DB::transaction(function () use ($incident, $attributes, $versionIds) {
            $this->assertVersionsBelongToProduct($incident->product, $versionIds);

            $incident->update($attributes);
            $incident->versions()->sync($versionIds);

            return $incident->fresh(['owner', 'versions']);
        });
    }

    public function delete(ProductIncident $incident): void
    {
        $incident->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function listItemPayload(ProductIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => $incident->status->value,
            'severity' => $incident->severity->value,
            'owner_name' => $incident->owner?->name,
            'awareness_at' => $incident->awareness_at?->toIso8601String(),
            'detected_at' => $incident->detected_at?->toIso8601String(),
            'classified_at' => $incident->classified_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ProductIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => $incident->status->value,
            'severity' => $incident->severity->value,
            'summary' => $incident->summary,
            'root_cause' => $incident->root_cause,
            'corrective_measures' => $incident->corrective_measures,
            'lessons_learned' => $incident->lessons_learned,
            'product_vulnerability_id' => $incident->product_vulnerability_id,
            'owner_user_id' => $incident->owner_user_id,
            'actual_started_at' => $incident->actual_started_at?->format('Y-m-d\TH:i'),
            'detected_at' => $incident->detected_at?->format('Y-m-d\TH:i'),
            'awareness_at' => $incident->awareness_at?->format('Y-m-d\TH:i'),
            'classified_at' => $incident->classified_at?->format('Y-m-d\TH:i'),
            'closed_at' => $incident->closed_at?->format('Y-m-d\TH:i'),
            'notes' => $incident->notes,
            'version_ids' => $incident->versions->pluck('id')->all(),
        ];
    }

    /**
     * @param  list<int>  $versionIds
     */
    private function assertVersionsBelongToProduct(Product $product, array $versionIds): void
    {
        if ($versionIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $versionIds)));
        $count = ProductVersion::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'version_ids' => ['One or more versions do not belong to this product.'],
            ]);
        }
    }
}

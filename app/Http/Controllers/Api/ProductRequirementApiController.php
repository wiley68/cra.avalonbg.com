<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Services\ProductRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRequirementApiController extends Controller
{
    public function __construct(
        private readonly ProductRequirementService $requirements,
    ) {}

    public function index(Request $request, Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [ProductRequirement::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        $this->requirements->ensureMatrix($product);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,code,article_ref,status,reviewed_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sortBy = $validated['sort_by'] ?? 'code';
        $sortOrder = (($validated['sort_desc'] ?? '0') === '1') ? 'desc' : 'asc';
        $search = trim((string) ($validated['search'] ?? ''));

        $query = ProductRequirement::query()
            ->where('product_id', $product->id)
            ->with(['requirement.regulation', 'requirementVersion', 'owner']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->whereHas('requirement', function ($requirementQuery) use ($search): void {
                    $requirementQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('article_ref', 'like', "%{$search}%");
                })->orWhere('status', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $sortMap = [
            'code' => 'requirements.code',
            'article_ref' => 'requirements.article_ref',
            'status' => 'product_requirements.status',
            'reviewed_at' => 'product_requirements.reviewed_at',
            'id' => 'product_requirements.id',
        ];

        if (in_array($sortBy, ['code', 'article_ref'], true)) {
            $query->join('requirements', 'requirements.id', '=', 'product_requirements.requirement_id')
                ->select('product_requirements.*')
                ->orderBy($sortMap[$sortBy], $sortOrder);
        } else {
            $query->orderBy($sortMap[$sortBy] ?? 'product_requirements.id', $sortOrder);
        }

        $rows = $query
            ->paginate($perPage, ['product_requirements.*'], 'page', $page)
            ->through(fn (ProductRequirement $row) => $this->requirements->listItemPayload($row));

        return response()->json($rows);
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertProductInOrganization(Product $product, Organization $organization): void
    {
        if ($product->organization_id !== $organization->id) {
            abort(404);
        }
    }
}

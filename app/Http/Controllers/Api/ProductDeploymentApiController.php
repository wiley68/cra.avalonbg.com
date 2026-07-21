<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ProductDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductDeploymentApiController extends Controller
{
    public function __construct(
        private readonly ProductDeploymentService $deployments,
    ) {
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();

        if ($product->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('view', [$product, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,customer_name,environment,installation_date,version_number,internet_exposure,support_status,security_support_deadline',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
            'unsupported_only' => 'in:0,1',
        ]);

        $unsupportedOnly = ($validated['unsupported_only'] ?? '0') === '1';

        $paginator = $this->deployments->paginate(
            $product,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'id',
            (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
            $unsupportedOnly,
        );

        return response()->json($paginator);
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }
}

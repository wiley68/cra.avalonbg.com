<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\TechnicalDocumentationPackage;
use App\Services\TechnicalDocumentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TechnicalDocumentationApiController extends Controller
{
    public function __construct(
        private readonly TechnicalDocumentationService $packages,
    ) {
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();

        if ($product->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('viewAny', [TechnicalDocumentationPackage::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,title,status,version_label,locale,published_at,updated_at,product_version_number',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
            'product_version_id' => [
                'nullable',
                'integer',
                Rule::exists('product_versions', 'id')->where(
                    fn($query) => $query->where('product_id', $product->id),
                ),
            ],
            'product_wide' => 'nullable|in:0,1',
        ]);

        $paginator = $this->packages->paginate(
            $product,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'updated_at',
            (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
            isset($validated['product_version_id']) ? (int) $validated['product_version_id'] : null,
            ($validated['product_wide'] ?? '0') === '1',
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

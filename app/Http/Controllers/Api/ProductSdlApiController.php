<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SdlRun;
use App\Services\ProductSdlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSdlApiController extends Controller
{
    public function __construct(
        private readonly ProductSdlService $sdl,
    ) {
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();

        if ($product->organization_id !== $organization->id) {
            abort(404);
        }

        $this->authorize('viewAny', [SdlRun::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,title,status,current_stage,approved_at,updated_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $paginator = $this->sdl->paginate(
            $product,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'title',
            (($validated['sort_desc'] ?? '0') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\TechnicalDocumentationPackage;
use App\Services\TechnicalDocumentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicalDocumentationOrgApiController extends Controller
{
    public function __construct(
        private readonly TechnicalDocumentationService $packages,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [TechnicalDocumentationPackage::class, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,title,status,version_label,locale,published_at,updated_at,product_version_number,product_name',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $paginator = $this->packages->paginateForOrganization(
            $organization,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'updated_at',
            (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc',
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

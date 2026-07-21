<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditorReviewPackageStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Services\AuditorReviewPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditorReviewPackageApiController extends Controller
{
    public function __construct(
        private readonly AuditorReviewPackageService $packages,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [AuditorReviewPackage::class, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,title,status,product_name,shared_at,closed_at,updated_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
            'product_id' => 'nullable|integer',
            'status' => ['nullable', 'string', Rule::enum(AuditorReviewPackageStatus::class)],
        ]);

        $status = isset($validated['status']) && $validated['status'] !== ''
            ? AuditorReviewPackageStatus::from($validated['status'])
            : null;

        $paginator = $this->packages->paginate(
            $organization,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'updated_at',
            (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
            isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            $status,
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

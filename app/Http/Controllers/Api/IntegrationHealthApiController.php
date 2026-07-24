<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\IntegrationHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationHealthApiController extends Controller
{
    public function __construct(
        private readonly IntegrationHealthService $health,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organization = $this->currentOrganization();

        if (!$user->canManageProducts($organization) && !$user->canViewProducts($organization)) {
            abort(403);
        }

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:provider,product_name,target,connection_status,last_synced_at,health,last_error,pending_suggestions',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $paginator = $this->health->paginateForOrganization(
            $organization,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'health',
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

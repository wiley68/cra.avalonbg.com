<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUserApiController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $memberships,
    ) {
    }

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', [User::class, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,name,email,role_slug,must_change_password',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $paginator = $this->memberships->paginateMembers(
            $organization,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'name',
            (($validated['sort_desc'] ?? '0') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
        );

        return response()->json($paginator);
    }
}

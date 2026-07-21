<?php

namespace App\Http\Controllers\Api;

use App\Enums\PolicyType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Services\OrgPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgPolicyApiController extends Controller
{
    public function __construct(
        private readonly OrgPolicyService $policies,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [OrgPolicy::class, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,title,policy_type,status,version_label,approved_at,updated_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
            'policy_type' => ['nullable', 'string', Rule::enum(PolicyType::class)],
        ]);

        $policyType = isset($validated['policy_type']) && $validated['policy_type'] !== ''
            ? PolicyType::from($validated['policy_type'])
            : null;

        $paginator = $this->policies->paginate(
            $organization,
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'updated_at',
            (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
            $policyType,
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

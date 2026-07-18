<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AuditLogApiController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogs,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization();
        Gate::authorize('viewAny', [AuditLog::class, $organization]);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,occurred_at,event_type,event_source,is_success,user_name,user_email',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
            'event_type' => ['nullable', 'string', Rule::enum(AuditEventType::class)],
            'event_source' => ['nullable', 'string', Rule::enum(AuditEventSource::class)],
            'is_success' => 'nullable|in:0,1',
        ]);

        $paginator = $this->auditLogs->paginate(
            (int) ($validated['per_page'] ?? 10),
            (int) ($validated['page'] ?? 1),
            $validated['sort_by'] ?? 'occurred_at',
            (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc',
            trim((string) ($validated['search'] ?? '')),
            $validated['event_type'] ?? null,
            $validated['event_source'] ?? null,
            $validated['is_success'] ?? null,
            $organization,
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

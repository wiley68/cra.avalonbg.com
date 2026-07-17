<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AuditLogApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

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

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sortBy = $validated['sort_by'] ?? 'occurred_at';
        $sortOrder = (($validated['sort_desc'] ?? '1') === '1') ? 'desc' : 'asc';
        $search = trim((string) ($validated['search'] ?? ''));
        $eventType = $validated['event_type'] ?? null;
        $eventSource = $validated['event_source'] ?? null;
        $isSuccess = $validated['is_success'] ?? null;

        $query = AuditLog::query()->select([
            'id',
            'occurred_at',
            'event_type',
            'event_source',
            'is_success',
            'user_id',
            'user_email',
            'user_name',
            'description',
        ]);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search)
                        ->orWhere('user_id', (int) $search);
                }
            });
        }

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        if ($eventSource !== null) {
            $query->where('event_source', $eventSource);
        }

        if ($isSuccess !== null) {
            $query->where('is_success', $isSuccess === '1');
        }

        $logs = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        $logs->getCollection()->transform(function (AuditLog $log) {
            $details = json_decode((string) $log->description, true);

            return [
                'id' => $log->id,
                'occurred_at' => $log->occurred_at?->format('Y-m-d H:i:s'),
                'event_type' => $log->event_type->value,
                'event_type_label' => $log->event_type->label(),
                'event_source' => $log->event_source->value,
                'event_source_label' => $log->event_source->label(),
                'is_success' => $log->is_success,
                'user_id' => $log->user_id,
                'user_email' => $log->user_email,
                'user_name' => $log->user_name,
                'details' => is_array($details) ? $details : [],
                'details_count' => is_array($details) ? count($details) : 0,
            ];
        });

        return response()->json($logs);
    }
}

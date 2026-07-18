<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'occurred_at',
        string $sortOrder = 'desc',
        string $search = '',
        ?string $eventType = null,
        ?string $eventSource = null,
        ?string $isSuccess = null,
        ?Organization $organization = null,
    ): LengthAwarePaginator {
        $query = AuditLog::query()->select([
            'id',
            'occurred_at',
            'event_type',
            'event_source',
            'is_success',
            'organization_id',
            'product_id',
            'user_id',
            'user_email',
            'user_name',
            'description',
        ]);

        if ($organization !== null) {
            $query->where('organization_id', $organization->id);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search)
                        ->orWhere('user_id', (int) $search)
                        ->orWhere('product_id', (int) $search);
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

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'event_type' => 'event_type',
            'event_source' => 'event_source',
            'is_success' => 'is_success',
            'user_name' => 'user_name',
            'user_email' => 'user_email',
            default => 'occurred_at',
        };

        return $query
            ->orderBy($orderColumn, $sortOrder === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(AuditLog $log) => $this->listItemPayload($log));
    }

    /**
     * @return array<string, mixed>
     */
    public function listItemPayload(AuditLog $log): array
    {
        $details = json_decode((string) $log->description, true);

        return [
            'id' => $log->id,
            'occurred_at' => $log->occurred_at?->format('Y-m-d H:i:s'),
            'event_type' => $log->event_type->value,
            'event_type_label' => $log->event_type->label(),
            'event_source' => $log->event_source->value,
            'event_source_label' => $log->event_source->label(),
            'is_success' => $log->is_success,
            'organization_id' => $log->organization_id,
            'product_id' => $log->product_id,
            'user_id' => $log->user_id,
            'user_email' => $log->user_email,
            'user_name' => $log->user_name,
            'details' => is_array($details) ? $details : [],
            'details_count' => is_array($details) ? count($details) : 0,
        ];
    }
}

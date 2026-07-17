<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,name,slug,is_active,users_count,billing_email,created_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortOrder = (($validated['sort_desc'] ?? '0') === '1') ? 'desc' : 'asc';
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Organization::query()->withCount('users');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('billing_email', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        if ($sortBy === 'users_count') {
            $query->orderBy('users_count', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $organizations = $query
            ->paginate($perPage, ['id', 'name', 'slug', 'is_active', 'billing_email', 'subscription_plan', 'created_at'], 'page', $page)
            ->through(fn(Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'is_active' => (bool) $organization->is_active,
                'billing_email' => $organization->billing_email,
                'subscription_plan' => $organization->subscription_plan,
                'users_count' => $organization->users_count,
                'created_at' => $organization->created_at?->toIso8601String(),
            ]);

        return response()->json($organizations);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Requirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequirementApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Requirement::class);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,code,article_ref,sort_order,is_active,created_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $sortBy = $validated['sort_by'] ?? 'sort_order';
        $sortOrder = (($validated['sort_desc'] ?? '0') === '1') ? 'desc' : 'asc';
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Requirement::query()
            ->with(['regulation', 'currentVersion']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('article_ref', 'like', "%{$search}%")
                    ->orWhereHas('currentVersion', function ($versionQuery) use ($search): void {
                        $versionQuery
                            ->where('requirement_text', 'like', "%{$search}%")
                            ->orWhere('plain_language', 'like', "%{$search}%")
                            ->orWhere('requirement_text_bg', 'like', "%{$search}%")
                            ->orWhere('plain_language_bg', 'like', "%{$search}%");
                    });

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $requirements = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(Requirement $requirement) => [
                'id' => $requirement->id,
                'code' => $requirement->code,
                'article_ref' => $requirement->article_ref,
                'sort_order' => $requirement->sort_order,
                'is_active' => $requirement->is_active,
                'regulation_code' => $requirement->regulation?->code,
                'plain_language' => $requirement->currentVersion?->localized('plain_language'),
                'version' => $requirement->currentVersion?->version,
                'created_at' => $requirement->created_at?->toIso8601String(),
            ]);

        return response()->json($requirements);
    }
}

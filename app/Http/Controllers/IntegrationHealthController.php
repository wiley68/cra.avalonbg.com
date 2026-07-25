<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\IntegrationHealthExportService;
use App\Services\OpsQueueHealthHintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class IntegrationHealthController extends Controller
{
    public function __construct(
        private readonly IntegrationHealthExportService $exports,
        private readonly OpsQueueHealthHintService $opsQueueHints,
    ) {
    }

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        if (!$user->canManageProducts($organization) && !$user->canViewProducts($organization)) {
            abort(403);
        }

        return Inertia::render('integrations/Health', [
            'organization' => $this->organizationPayload($organization),
            'canManage' => $user->canManageProducts($organization),
            'opsQueueHint' => $this->opsQueueHints->hintForOrganization($organization),
            'backUrl' => $this->resolveBackUrl($request->string('from')->toString()),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();

        if ($organization === null) {
            abort(404);
        }

        if (!$user->canManageProducts($organization) && !$user->canViewProducts($organization)) {
            abort(403);
        }

        return $this->exports->export($organization, $format, $user);
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * Safe relative return path for the Back button (prevents open redirects).
     */
    private function resolveBackUrl(string $from): string
    {
        $default = route('settings.integrations.edit', absolute: false);

        if ($from === '') {
            return $default;
        }

        if (!str_starts_with($from, '/') || str_starts_with($from, '//') || str_contains($from, '\\')) {
            return $default;
        }

        $path = parse_url($from, PHP_URL_PATH);

        if (!is_string($path) || $path === '' || str_starts_with($path, '/integrations/health')) {
            return $default;
        }

        return $from;
    }
}

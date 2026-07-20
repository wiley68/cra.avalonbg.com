<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationVcsConnection;
use App\Services\GithubWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GithubWebhookController extends Controller
{
    public function __construct(
        private readonly GithubWebhookService $webhooks,
    ) {
    }

    public function __invoke(Request $request, OrganizationVcsConnection $connection): JsonResponse
    {
        $result = $this->webhooks->handle($connection, $request);

        return response()->json($result, $result['dispatched'] ? 202 : 200);
    }
}

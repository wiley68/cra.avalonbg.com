<?php

namespace App\Services;

use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Jobs\SyncProductRepositoryJob;
use App\Models\OrganizationVcsConnection;
use App\Models\ProductRepository;
use App\Models\VcsWebhookDelivery;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GithubWebhookService
{
    /**
     * Events that should trigger a repository sync.
     *
     * @var list<string>
     */
    private const SYNC_EVENTS = [
        'release',
        'create',
        'workflow_run',
        'dependabot_alert',
        'push',
    ];

    /**
     * @return array{status: string, repository_id: int|null, dispatched: bool}
     */
    public function handle(OrganizationVcsConnection $connection, Request $request): array
    {
        if (
            $connection->provider !== VcsProvider::Github
            || $connection->status !== VcsConnectionStatus::Active
        ) {
            throw new HttpException(404, 'Connection not available.');
        }

        if (blank($connection->webhook_secret)) {
            throw new HttpException(503, 'Webhook secret is not configured.');
        }

        $this->assertValidSignature($request, $connection->webhook_secret);

        $deliveryId = (string) $request->header('X-GitHub-Delivery', '');
        $event = (string) $request->header('X-GitHub-Event', '');

        if ($deliveryId === '' || $event === '') {
            throw new AccessDeniedHttpException('Missing GitHub webhook headers.');
        }

        if (VcsWebhookDelivery::query()->where('delivery_id', $deliveryId)->exists()) {
            return [
                'status' => 'duplicate',
                'repository_id' => null,
                'dispatched' => false,
            ];
        }

        if ($event === 'ping') {
            $this->recordDelivery($connection, $deliveryId, $event, null, 'ignored');

            return [
                'status' => 'ping',
                'repository_id' => null,
                'dispatched' => false,
            ];
        }

        if (!in_array($event, self::SYNC_EVENTS, true)) {
            $this->recordDelivery($connection, $deliveryId, $event, null, 'ignored');

            return [
                'status' => 'ignored',
                'repository_id' => null,
                'dispatched' => false,
            ];
        }

        if ($event === 'create' && ($request->input('ref_type') !== 'tag')) {
            $this->recordDelivery($connection, $deliveryId, $event, null, 'ignored');

            return [
                'status' => 'ignored',
                'repository_id' => null,
                'dispatched' => false,
            ];
        }

        $repository = $this->resolveRepository($connection, $request);

        if ($repository === null) {
            $this->recordDelivery($connection, $deliveryId, $event, null, 'unmatched');

            return [
                'status' => 'unmatched',
                'repository_id' => null,
                'dispatched' => false,
            ];
        }

        SyncProductRepositoryJob::dispatch($repository->id);
        $this->recordDelivery($connection, $deliveryId, $event, $repository->id, 'dispatched');

        return [
            'status' => 'dispatched',
            'repository_id' => $repository->id,
            'dispatched' => true,
        ];
    }

    private function assertValidSignature(Request $request, string $secret): void
    {
        $header = (string) $request->header('X-Hub-Signature-256', '');

        if ($header === '' || !str_starts_with($header, 'sha256=')) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $header)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }
    }

    private function resolveRepository(OrganizationVcsConnection $connection, Request $request): ?ProductRepository
    {
        $repoPayload = $request->input('repository');

        if (!is_array($repoPayload)) {
            return null;
        }

        $externalId = isset($repoPayload['id']) ? (string) $repoPayload['id'] : null;
        $fullName = isset($repoPayload['full_name']) && is_string($repoPayload['full_name'])
            ? $repoPayload['full_name']
            : null;

        $query = ProductRepository::query()->where('connection_id', $connection->id);

        if ($externalId !== null && $externalId !== '') {
            $byId = (clone $query)->where('external_id', $externalId)->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        if ($fullName !== null && $fullName !== '') {
            return $query->where('full_name', $fullName)->first();
        }

        return null;
    }

    private function recordDelivery(
        OrganizationVcsConnection $connection,
        string $deliveryId,
        string $event,
        ?int $repositoryId,
        string $status,
    ): void {
        VcsWebhookDelivery::query()->create([
            'connection_id' => $connection->id,
            'delivery_id' => $deliveryId,
            'event' => $event,
            'repository_id' => $repositoryId,
            'status' => $status,
        ]);
    }
}

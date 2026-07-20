<?php

namespace App\Services;

use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ProductRepositoryService
{
    public function link(
        Product $product,
        OrganizationVcsConnection $connection,
        string $repositoryInput,
        User $actor,
    ): ProductRepository {
        if ($connection->organization_id !== $product->organization_id) {
            abort(404);
        }

        if (
            $connection->provider !== VcsProvider::Github
            || $connection->status !== VcsConnectionStatus::Active
        ) {
            throw ValidationException::withMessages([
                'connection_id' => [Translations::get('products.repository.connection_unavailable')],
            ]);
        }

        $fullName = $this->normalizeFullName($repositoryInput);
        $remote = $this->fetchGithubRepository($connection, $fullName);

        $existing = $product->repository;
        $attributes = [
            'connection_id' => $connection->id,
            'external_id' => $remote['external_id'],
            'full_name' => $remote['full_name'],
            'remote_url' => $remote['remote_url'],
            'default_branch' => $remote['default_branch'],
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $repository = $existing->fresh(['product']);
            AuditLogger::logVcsRepositoryLinked($repository, $actor);

            return $repository;
        }

        $repository = ProductRepository::query()->create([
            'product_id' => $product->id,
            ...$attributes,
        ]);

        AuditLogger::logVcsRepositoryLinked($repository->load('product'), $actor);

        return $repository;
    }

    public function unlink(ProductRepository $repository, User $actor): void
    {
        $repository->loadMissing('product');
        AuditLogger::logVcsRepositoryUnlinked($repository, $actor);
        $repository->delete();
    }

    /**
     * @return array{id: int, full_name: string, remote_url: string, default_branch: string|null, connection_id: int, external_id: string|null, last_synced_at: string|null, last_sync_summary: array<string, mixed>|null}
     */
    public function payload(?ProductRepository $repository): ?array
    {
        if ($repository === null) {
            return null;
        }

        return [
            'id' => $repository->id,
            'full_name' => $repository->full_name,
            'remote_url' => $repository->remote_url,
            'default_branch' => $repository->default_branch,
            'connection_id' => $repository->connection_id,
            'external_id' => $repository->external_id,
            'last_synced_at' => $repository->last_synced_at?->toIso8601String(),
            'last_sync_summary' => $repository->last_sync_summary,
        ];
    }

    public function normalizeFullName(string $input): string
    {
        $value = trim($input);

        if (preg_match('~^https?://(?:www\.)?github\.com/([^/]+)/([^/?#]+)~i', $value, $matches) === 1) {
            return $this->cleanOwnerRepo($matches[1], $matches[2]);
        }

        if (preg_match('~^git@github\.com:([^/]+)/([^/?#]+)$~i', $value, $matches) === 1) {
            return $this->cleanOwnerRepo($matches[1], $matches[2]);
        }

        if (preg_match('~^([^/\s]+)/([^/\s]+)$~', $value, $matches) === 1) {
            return $this->cleanOwnerRepo($matches[1], $matches[2]);
        }

        throw ValidationException::withMessages([
            'repository' => [Translations::get('products.repository.invalid_format')],
        ]);
    }

    /**
     * @return array{external_id: string, full_name: string, remote_url: string, default_branch: string|null}
     */
    private function fetchGithubRepository(OrganizationVcsConnection $connection, string $fullName): array
    {
        $response = Http::withToken($connection->token)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRA-Compliance-Workspace',
            ])
            ->get('https://api.github.com/repos/' . $fullName);

        if ($response->status() === 404) {
            throw ValidationException::withMessages([
                'repository' => [Translations::get('products.repository.not_found')],
            ]);
        }

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'repository' => [Translations::get('products.repository.fetch_failed')],
            ]);
        }

        /** @var array{id?: int|string, full_name?: string, html_url?: string, default_branch?: string} $data */
        $data = $response->json();

        $resolvedFullName = $data['full_name'] ?? $fullName;

        return [
            'external_id' => isset($data['id']) ? (string) $data['id'] : '',
            'full_name' => $resolvedFullName,
            'remote_url' => $data['html_url'] ?? ('https://github.com/' . $resolvedFullName),
            'default_branch' => $data['default_branch'] ?? null,
        ];
    }

    private function cleanOwnerRepo(string $owner, string $repo): string
    {
        $repo = preg_replace('/\.git$/i', '', $repo) ?? $repo;

        return $owner . '/' . $repo;
    }
}

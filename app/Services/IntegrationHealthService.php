<?php

namespace App\Services;

use App\Enums\ImportSuggestionStatus;
use App\Enums\VcsImportSuggestionStatus;
use App\Models\Organization;
use App\Models\ProductIntegrationLink;
use App\Models\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use DateTimeInterface;

class IntegrationHealthService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForOrganization(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'health',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $rows = $this->rowsForOrganization($organization);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string) ($row['provider'] ?? ''),
                    (string) ($row['product_name'] ?? ''),
                    (string) ($row['target'] ?? ''),
                    (string) ($row['connection_status'] ?? ''),
                    (string) ($row['health'] ?? ''),
                    (string) ($row['last_error'] ?? ''),
                    (string) ($row['source'] ?? ''),
                ])));

                return str_contains($haystack, $needle);
            })->values();
        }

        $rows = $this->sortRows($rows, $sortBy, $sortOrder);

        $total = $rows->count();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        return new Paginator(
            $rows->slice($offset, $perPage)->values()->all(),
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rowsForOrganization(Organization $organization): Collection
    {
        $links = ProductIntegrationLink::query()
            ->with([
                'product:id,name,organization_id',
                'integration:id,organization_id,provider,status,label',
            ])
            ->whereHas('product', fn($query) => $query->where('organization_id', $organization->id))
            ->withCount([
                'importSuggestions as pending_suggestions_count' => fn($query) => $query
                    ->where('status', ImportSuggestionStatus::Pending),
            ])
            ->get();

        $linkRows = $links->map(function (ProductIntegrationLink $link): array {
            $summary = is_array($link->last_sync_summary) ? $link->last_sync_summary : [];
            $provider = $link->integration?->provider;
            $status = $link->integration?->status;
            $target = $link->external_label
                ?: $link->external_project_key
                ?: $link->external_target_id
                ?: '—';

            return [
                'id' => 'link:' . $link->id,
                'source' => 'integration',
                'source_id' => $link->id,
                'provider' => is_object($provider) ? $provider->value : (string) ($provider ?? ''),
                'product_id' => $link->product_id,
                'product_name' => (string) ($link->product?->name ?? ''),
                'target' => (string) $target,
                'connection_status' => is_object($status) ? $status->value : (string) ($status ?? ''),
                'last_synced_at' => $link->last_synced_at?->toIso8601String(),
                'health' => $this->deriveHealth($summary, $link->last_synced_at),
                'last_error' => $this->extractError($summary),
                'pending_suggestions' => (int) $link->pending_suggestions_count,
            ];
        });

        $repos = ProductRepository::query()
            ->with([
                'product:id,name,organization_id',
                'connection:id,organization_id,provider,status,label',
            ])
            ->whereHas('product', fn($query) => $query->where('organization_id', $organization->id))
            ->withCount([
                'importSuggestions as pending_suggestions_count' => fn($query) => $query
                    ->where('status', VcsImportSuggestionStatus::Pending),
            ])
            ->get();

        $repoRows = $repos->map(function (ProductRepository $repository): array {
            $summary = is_array($repository->last_sync_summary) ? $repository->last_sync_summary : [];
            $provider = $repository->connection?->provider;
            $status = $repository->connection?->status;

            return [
                'id' => 'repo:' . $repository->id,
                'source' => 'vcs',
                'source_id' => $repository->id,
                'provider' => is_object($provider) ? $provider->value : (string) ($provider ?? ''),
                'product_id' => $repository->product_id,
                'product_name' => (string) ($repository->product?->name ?? ''),
                'target' => (string) $repository->full_name,
                'connection_status' => is_object($status) ? $status->value : (string) ($status ?? ''),
                'last_synced_at' => $repository->last_synced_at?->toIso8601String(),
                'health' => $this->deriveHealth($summary, $repository->last_synced_at),
                'last_error' => $this->extractError($summary),
                'pending_suggestions' => (int) $repository->pending_suggestions_count,
            ];
        });

        return $linkRows->concat($repoRows)->values();
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function deriveHealth(array $summary, ?DateTimeInterface $lastSyncedAt): string
    {
        $error = $this->extractError($summary);
        if ($error !== null) {
            return !empty($summary['soft_fail']) ? 'soft_fail' : 'failed';
        }

        if (!empty($summary['soft_fail'])) {
            return 'soft_fail';
        }

        return $lastSyncedAt !== null ? 'ok' : 'never';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function extractError(array $summary): ?string
    {
        foreach (['last_error', 'error'] as $key) {
            $value = $summary[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, string $sortBy, string $sortOrder): Collection
    {
        $allowed = [
            'provider',
            'product_name',
            'target',
            'connection_status',
            'last_synced_at',
            'health',
            'last_error',
            'pending_suggestions',
        ];

        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'health';
        }

        $healthRank = [
            'failed' => 0,
            'soft_fail' => 1,
            'never' => 2,
            'ok' => 3,
        ];

        $descending = $sortOrder === 'desc';

        return $rows->sort(function (array $a, array $b) use ($sortBy, $descending, $healthRank): int {
            $left = $a[$sortBy] ?? null;
            $right = $b[$sortBy] ?? null;

            if ($sortBy === 'health') {
                $left = $healthRank[(string) $left] ?? 99;
                $right = $healthRank[(string) $right] ?? 99;
            } elseif ($sortBy === 'pending_suggestions') {
                $left = (int) $left;
                $right = (int) $right;
            } elseif ($sortBy === 'last_synced_at') {
                $left = $left ? strtotime((string) $left) : 0;
                $right = $right ? strtotime((string) $right) : 0;
            } else {
                $left = mb_strtolower((string) ($left ?? ''));
                $right = mb_strtolower((string) ($right ?? ''));
            }

            $cmp = $left <=> $right;
            if ($cmp === 0) {
                $cmp = strcmp((string) $a['id'], (string) $b['id']);
            }

            return $descending ? -$cmp : $cmp;
        })->values();
    }
}

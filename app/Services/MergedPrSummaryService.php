<?php

namespace App\Services;

use App\Enums\VcsAuthType;
use App\Enums\VcsProvider as VcsProviderEnum;
use App\Models\Evidence;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Models\User;
use App\Services\Vcs\GitHubAppTokenService;
use App\Services\Vcs\GitHubPatProvider;
use App\Services\Vcs\GitLabPatProvider;
use App\Support\AuditLogger;
use App\Support\Translations;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MergedPrSummaryService
{
    public const CACHE_TTL_SECONDS = 900;

    public const RELEASE_WINDOW_DAYS = 14;

    public const FALLBACK_WINDOW_DAYS = 30;

    public const MAX_PRS = 30;

    public function __construct(
        private readonly GitHubAppTokenService $githubAppTokens,
        private readonly EvidenceService $evidence,
    ) {
    }

    /**
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     provider: string|null,
     *     repository_full_name: string|null,
     *     window: array{from: string, to: string, mode: string, anchor_date: string|null},
     *     cached_at: string|null,
     *     from_cache: bool,
     *     count: int,
     *     truncated: bool,
     *     prs: list<array{
     *         number: int,
     *         title: string,
     *         html_url: string,
     *         merged_at: string|null,
     *         user_login: string|null
     *     }>,
     *     error: string|null
     * }
     */
    public function summarize(
        Product $product,
        ProductVersion $version,
        bool $forceRefresh = false,
    ): array {
        $window = $this->resolveWindow($version);
        $empty = [
            'available' => false,
            'reason' => null,
            'provider' => null,
            'repository_full_name' => null,
            'window' => $window,
            'cached_at' => null,
            'from_cache' => false,
            'count' => 0,
            'truncated' => false,
            'prs' => [],
            'error' => null,
        ];

        $repository = $product->repository()->with('connection')->first();

        if ($repository === null) {
            return array_merge($empty, [
                'reason' => 'no_repository',
            ]);
        }

        $connection = $repository->connection;

        if ($connection === null) {
            return array_merge($empty, [
                'reason' => 'no_repository',
                'repository_full_name' => $repository->full_name,
            ]);
        }

        if (!in_array($connection->provider, [VcsProviderEnum::Github, VcsProviderEnum::Gitlab], true)) {
            return array_merge($empty, [
                'reason' => 'unsupported_provider',
                'provider' => $connection->provider->value,
                'repository_full_name' => $repository->full_name,
            ]);
        }

        $provider = $connection->provider->value;
        $cacheKey = $this->cacheKey(
            $provider,
            $product->id,
            $version->id,
            $window['from'],
            $window['to'],
        );

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $fromCache = !$forceRefresh && Cache::has($cacheKey);

        try {
            /** @var array{cached_at: string, count: int, truncated: bool, prs: list<array{number: int, title: string, html_url: string, merged_at: string|null, user_login: string|null}>} $payload */
            $payload = Cache::remember(
                $cacheKey,
                self::CACHE_TTL_SECONDS,
                function () use ($repository, $connection, $window): array {
                    $prs = $this->fetchMergedPulls($repository, $connection, $window['from'], $window['to']);

                    return [
                        'cached_at' => now()->toIso8601String(),
                        'count' => count($prs),
                        'truncated' => count($prs) >= self::MAX_PRS,
                        'prs' => $prs,
                    ];
                },
            );
        } catch (Throwable $exception) {
            return array_merge($empty, [
                'available' => false,
                'reason' => 'fetch_failed',
                'provider' => $provider,
                'repository_full_name' => $repository->full_name,
                'error' => Translations::get('products.versions.merged_prs.fetch_failed'),
            ]);
        }

        return [
            'available' => true,
            'reason' => null,
            'provider' => $provider,
            'repository_full_name' => $repository->full_name,
            'window' => $window,
            'cached_at' => $payload['cached_at'],
            'from_cache' => $fromCache,
            'count' => $payload['count'],
            'truncated' => $payload['truncated'],
            'prs' => $payload['prs'],
            'error' => null,
        ];
    }

    /**
     * Snapshot the current merged PR/MR summary as immutable Markdown evidence.
     */
    public function saveAsEvidence(
        Product $product,
        ProductVersion $version,
        User $actor,
    ): Evidence {
        $summary = $this->summarize($product, $version);

        if (!$summary['available']) {
            throw ValidationException::withMessages([
                'merged_prs' => [
                    $summary['error']
                    ?? Translations::get(
                        'products.versions.merged_prs.reasons.' . ($summary['reason'] ?? 'fetch_failed'),
                    ),
                ],
            ]);
        }

        return DB::transaction(function () use ($product, $version, $actor, $summary): Evidence {
            $body = $this->toMarkdown($product, $version, $summary);
            $evidence = $this->evidence->createFromMergedPrSummary(
                $product,
                $version,
                $actor,
                $summary,
                $body,
            );

            AuditLogger::logMergedPrSummarySavedAsEvidence(
                $product,
                $version,
                $evidence,
                $actor,
                $summary,
            );

            return $evidence;
        });
    }

    /**
     * @return array{id: int, title: string}|null
     */
    public function latestSavedEvidenceSummary(ProductVersion $version): ?array
    {
        $evidence = Evidence::query()
            ->where('product_version_id', $version->id)
            ->where('source', 'like', 'merged_pr_summary:version:' . $version->id . ':%')
            ->latest('id')
            ->first(['id', 'title']);

        if ($evidence === null) {
            return null;
        }

        return [
            'id' => $evidence->id,
            'title' => $evidence->title,
        ];
    }

    /**
     * @return array{from: string, to: string, mode: string, anchor_date: string|null}
     */
    public function resolveWindow(ProductVersion $version, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();

        if ($version->release_date !== null) {
            $anchor = $version->release_date->copy()->startOfDay();

            return [
                'from' => $anchor->copy()->subDays(self::RELEASE_WINDOW_DAYS)->toDateString(),
                'to' => $anchor->copy()->addDays(self::RELEASE_WINDOW_DAYS)->toDateString(),
                'mode' => 'release_window',
                'anchor_date' => $anchor->toDateString(),
            ];
        }

        return [
            'from' => $now->copy()->subDays(self::FALLBACK_WINDOW_DAYS)->toDateString(),
            'to' => $now->toDateString(),
            'mode' => 'rolling_30_days',
            'anchor_date' => null,
        ];
    }

    /**
     * @param  array{
     *     provider: string|null,
     *     repository_full_name: string|null,
     *     window: array{from: string, to: string, mode: string, anchor_date: string|null},
     *     cached_at: string|null,
     *     count: int,
     *     truncated: bool,
     *     prs: list<array{
     *         number: int,
     *         title: string,
     *         html_url: string,
     *         merged_at: string|null,
     *         user_login: string|null
     *     }>
     * }  $summary
     */
    public function toMarkdown(Product $product, ProductVersion $version, array $summary): string
    {
        $lines = [
            '# Merged pull requests — ' . $product->name . ' ' . $version->version_number,
            '',
            '- Product: ' . $product->name,
            '- Version: ' . $version->version_number,
            '- Repository: ' . ($summary['repository_full_name'] ?? '—'),
            '- Provider: ' . ($summary['provider'] ?? '—'),
            '- Window: ' . $summary['window']['from'] . ' – ' . $summary['window']['to']
            . ' (' . $summary['window']['mode'] . ')',
            '- Count: ' . $summary['count'] . ($summary['truncated'] ? ' (truncated)' : ''),
        ];

        if ($summary['cached_at'] !== null) {
            $lines[] = '- Snapshot source cached at: ' . $summary['cached_at'];
        }

        $lines[] = '- Saved at: ' . now()->toIso8601String();
        $lines[] = '';
        $lines[] = '## Pull requests';
        $lines[] = '';

        if ($summary['prs'] === []) {
            $lines[] = '_No merged pull requests in this window._';
            $lines[] = '';

            return implode("\n", $lines);
        }

        $lines[] = '| # | Title | Author | Merged at | URL |';
        $lines[] = '| --- | --- | --- | --- | --- |';

        foreach ($summary['prs'] as $pr) {
            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s |',
                $pr['number'],
                $this->escapeMarkdownCell($pr['title']),
                $this->escapeMarkdownCell($pr['user_login'] ?? '—'),
                $pr['merged_at'] ?? '—',
                $pr['html_url'],
            );
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return list<array{
     *     number: int,
     *     title: string,
     *     html_url: string,
     *     merged_at: string|null,
     *     user_login: string|null
     * }>
     */
    private function fetchMergedPulls(
        ProductRepository $repository,
        OrganizationVcsConnection $connection,
        string $from,
        string $to,
    ): array {
        return match ($connection->provider) {
            VcsProviderEnum::Github => (new GitHubPatProvider($this->githubAccessToken($connection)))
                ->listMergedPulls($repository->full_name, $from, $to, self::MAX_PRS),
            VcsProviderEnum::Gitlab => (new GitLabPatProvider((string) $connection->token))
                ->listMergedPulls($repository->full_name, $from, $to, self::MAX_PRS),
            default => throw new \RuntimeException('Unsupported VCS provider for merged PR summary.'),
        };
    }

    private function githubAccessToken(OrganizationVcsConnection $connection): string
    {
        return match ($connection->auth_type) {
            VcsAuthType::GithubApp => $this->githubAppTokens->installationAccessToken($connection),
            VcsAuthType::Pat => (string) $connection->token,
        };
    }

    private function cacheKey(
        string $provider,
        int $productId,
        int $versionId,
        string $from,
        string $to,
    ): string {
        return "merged_pr_summary:{$provider}:{$productId}:{$versionId}:{$from}:{$to}";
    }

    private function escapeMarkdownCell(string $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], $value);
    }
}

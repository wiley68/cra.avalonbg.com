<?php

namespace App\Services;

use App\Enums\VcsAuthType;
use App\Enums\VcsProvider as VcsProviderEnum;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Services\Vcs\GitHubAppTokenService;
use App\Services\Vcs\GitHubPatProvider;
use App\Services\Vcs\GitLabPatProvider;
use App\Support\Translations;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MergedPrSummaryService
{
    public const CACHE_TTL_SECONDS = 900;

    public const RELEASE_WINDOW_DAYS = 14;

    public const FALLBACK_WINDOW_DAYS = 30;

    public const MAX_PRS = 30;

    public function __construct(
        private readonly GitHubAppTokenService $githubAppTokens,
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
}

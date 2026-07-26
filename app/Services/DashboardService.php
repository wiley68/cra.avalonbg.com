<?php

namespace App\Services;

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IncidentStatus;
use App\Enums\ProductVersionState;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Enums\SupportPeriodStartBasis;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityStatus;
use App\Models\Evidence;
use App\Models\ImportSuggestion;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductIntegrationLink;
use App\Models\ProductRisk;
use App\Models\ProductSupportPeriod;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\SdlRun;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    private const OPEN_TASKS_PREVIEW_LIMIT = 3;

    private const RECENT_PRODUCTS_LIMIT = 3;

    private const RECENT_RISKS_LIMIT = 3;

    private const CRITICAL_VULNERABILITIES_PREVIEW_LIMIT = 3;

    public function __construct(
        private readonly ProductReadinessService $readiness,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        if ($user->isPlatformAdmin() && $user->currentOrganization() === null) {
            return $this->platformDashboard();
        }

        $organization = $user->currentOrganization();

        if ($organization === null) {
            return $this->emptyDashboard();
        }

        return $this->organizationDashboard($organization, $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function platformDashboard(): array
    {
        $organizationCount = Organization::query()->count();

        return [
            'mode' => 'platform',
            'organization' => null,
            'counts' => [
                'organizations' => $organizationCount,
                'products' => Product::query()->count(),
            ],
            'recent_products' => [],
            'recent_open_tasks' => [],
            'recent_risks' => [],
            'recent_critical_vulnerabilities' => [],
            'actions' => [
                [
                    'key' => 'manage_organizations',
                    'severity' => 'info',
                    'title_key' => 'dashboard.actions.manage_organizations',
                    'count' => $organizationCount,
                    'href' => route('admin.organizations.index'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'mode' => 'empty',
            'organization' => null,
            'counts' => [],
            'recent_products' => [],
            'recent_open_tasks' => [],
            'recent_risks' => [],
            'recent_critical_vulnerabilities' => [],
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationDashboard(Organization $organization, User $user): array
    {
        /** @var Collection<int, int|string> $productIds */
        $productIds = Product::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $criticalVulns = $this->criticalVulnerabilityCount($productIds);
        $expiredEvidence = $this->expiredEvidenceCount($productIds);
        $overdueReporting = $this->overdueReportingCount($productIds);
        $risksCount = ProductRisk::query()->whereIn('product_id', $productIds)->count();
        $openIncidents = $this->openIncidentCount($productIds);
        $unclassifiedIncidents = $this->unclassifiedIncidentCount($productIds);
        $recentOpenTasks = $this->recentOpenTasks($productIds);
        $openTasksAction = $this->openTasksAction($productIds, $recentOpenTasks);
        $supportBuckets = $this->supportEndingBuckets($productIds);
        $sdlPendingMonitoring = $this->sdlPendingMonitoringCount($organization);
        $sdlApproved = $this->sdlApprovedCount($organization);
        $pendingImportSuggestions = $this->pendingImportSuggestionCount($productIds);
        $failedIntegrationSyncs = $this->failedIntegrationSyncCount($productIds);

        $actions = array_values(array_filter([
            $this->countAction(
                'unclassified_products',
                'warn',
                $this->unclassifiedProductCount($organization),
            ),
            $this->countAction(
                'products_without_support',
                'warn',
                $this->productsWithoutSupportCount($organization),
            ),
            $this->countAction(
                'products_without_risks',
                'warn',
                $this->productsWithoutRisksCount($organization),
            ),
            $this->countAction(
                'critical_vulnerabilities',
                'fail',
                $criticalVulns,
            ),
            $this->countAction(
                'open_incidents',
                'warn',
                $openIncidents,
            ),
            $this->countAction(
                'unclassified_incidents',
                'warn',
                $unclassifiedIncidents,
            ),
            $this->countAction(
                'support_ending_180',
                'info',
                $supportBuckets[180],
            ),
            $this->countAction(
                'support_ending_90',
                'warn',
                $supportBuckets[90],
            ),
            $this->countAction(
                'support_ending_30',
                'fail',
                $supportBuckets[30],
            ),
            $this->countAction(
                'support_ended',
                'fail',
                $supportBuckets['ended'],
            ),
            $this->countAction(
                'expired_evidence',
                'fail',
                $expiredEvidence,
            ),
            $openTasksAction,
            $this->overdueTasksAction($productIds),
            $this->countAction(
                'releases_awaiting_approval',
                'warn',
                $this->releasesAwaitingApprovalCount($productIds),
            ),
            $this->countAction(
                'overdue_reporting',
                'fail',
                $overdueReporting,
            ),
            $this->pendingImportSuggestionsAction($productIds, $pendingImportSuggestions),
            $this->failedIntegrationSyncsAction($productIds, $failedIntegrationSyncs),
            $user->canViewSdl($organization)
            ? $this->countAction(
                'sdl_pending_monitoring',
                'warn',
                $sdlPendingMonitoring,
                route('sdl.index'),
            )
            : null,
        ]));

        return [
            'mode' => 'organization',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'counts' => [
                'products' => $productIds->count(),
                'open_tasks' => (int) ($openTasksAction['count'] ?? 0),
                'critical_vulnerabilities' => $criticalVulns,
                'expired_evidence' => $expiredEvidence,
                'risks' => $risksCount,
                'overdue_reporting' => $overdueReporting,
                'open_incidents' => $openIncidents,
                'unclassified_incidents' => $unclassifiedIncidents,
                'sdl_approved' => $sdlApproved,
                'sdl_pending_monitoring' => $sdlPendingMonitoring,
                'pending_import_suggestions' => $pendingImportSuggestions,
                'failed_integration_syncs' => $failedIntegrationSyncs,
            ],
            'recent_products' => $this->recentProducts($organization),
            'recent_open_tasks' => array_map(
                static fn(array $task): array => [
                    'id' => $task['id'],
                    'title' => $task['title'],
                    'href' => $task['href'],
                ],
                $recentOpenTasks,
            ),
            'recent_risks' => $this->recentRisks($productIds),
            'recent_critical_vulnerabilities' => $this->recentCriticalVulnerabilities($productIds),
            'actions' => $actions,
        ];
    }

    /**
     * @return list<array{id: int, name: string, status: 'empty'|'complete'|'attention'|'critical', href: string}>
     */
    private function recentProducts(Organization $organization): array
    {
        $products = Product::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_PRODUCTS_LIMIT)
            ->get();

        return $products
            ->map(function (Product $product): array {
                $statuses = $this->readiness->cardModuleStatuses($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $this->aggregateRecentProductStatus($statuses),
                    'href' => route('products.edit', $product),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, 'empty'|'complete'|'attention'|'critical'>  $statuses
     * @return 'empty'|'complete'|'attention'|'critical'
     */
    private function aggregateRecentProductStatus(array $statuses): string
    {
        $moduleKeys = [
            'versions',
            'support_periods',
            'deployments',
            'campaigns',
            'requirements',
            'controls',
            'risks',
            'components',
            'vulnerabilities',
            'incidents',
            'sdl',
            'evidence',
            'tasks',
            'passport',
            'readiness',
            'assistant',
            'security_instructions',
            'technical_documentation',
        ];

        $values = [];
        foreach ($moduleKeys as $key) {
            $values[] = $statuses[$key] ?? 'empty';
        }

        if (in_array('critical', $values, true)) {
            return 'critical';
        }

        if (in_array('attention', $values, true)) {
            return 'attention';
        }

        if (in_array('complete', $values, true)) {
            return 'complete';
        }

        return 'empty';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function countAction(
        string $key,
        string $severity,
        int $count,
        ?string $href = null,
    ): ?array {
        if ($count <= 0) {
            return null;
        }

        return [
            'key' => $key,
            'severity' => $severity,
            'title_key' => 'dashboard.actions.' . $key,
            'count' => $count,
            'href' => $href ?? route('products.index'),
        ];
    }

    private function sdlApprovedCount(Organization $organization): int
    {
        return SdlRun::query()
            ->where('organization_id', $organization->id)
            ->where('status', SdlRunStatus::Approved)
            ->count();
    }

    private function sdlPendingMonitoringCount(Organization $organization): int
    {
        return SdlRun::query()
            ->where('organization_id', $organization->id)
            ->where('status', SdlRunStatus::Approved)
            ->whereHas('stageEntries', function ($query): void {
                $query
                    ->where('stage', SdlStage::Monitoring)
                    ->where('status', SdlStageStatus::Pending);
            })
            ->count();
    }

    private function unclassifiedProductCount(Organization $organization): int
    {
        return Product::query()
            ->where('organization_id', $organization->id)
            ->whereIn('classification_status', [
                ClassificationStatus::Unclassified->value,
                ClassificationStatus::UnderReview->value,
            ])
            ->count();
    }

    private function productsWithoutSupportCount(Organization $organization): int
    {
        return Product::query()
            ->where('organization_id', $organization->id)
            ->whereDoesntHave('supportPeriods')
            ->count();
    }

    private function productsWithoutRisksCount(Organization $organization): int
    {
        return Product::query()
            ->where('organization_id', $organization->id)
            ->whereDoesntHave('productRisks')
            ->count();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function criticalVulnerabilityCount(Collection $productIds): int
    {
        return ProductVulnerability::query()
            ->whereIn('product_id', $productIds)
            ->where('business_severity', VulnerabilityBusinessSeverity::Critical->value)
            ->whereNotIn('status', [
                VulnerabilityStatus::Closed->value,
                VulnerabilityStatus::Rejected->value,
            ])
            ->count();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function openIncidentCount(Collection $productIds): int
    {
        return ProductIncident::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', $this->activeIncidentStatusValues())
            ->count();
    }

    /**
     * Active incidents that still lack a classification timestamp.
     *
     * @param  Collection<int, int|string>  $productIds
     */
    private function unclassifiedIncidentCount(Collection $productIds): int
    {
        return ProductIncident::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', $this->activeIncidentStatusValues())
            ->whereNull('classified_at')
            ->count();
    }

    /**
     * @return list<string>
     */
    private function activeIncidentStatusValues(): array
    {
        return array_map(
            fn(IncidentStatus $status): string => $status->value,
            IncidentStatus::active(),
        );
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return array{180: int, 90: int, 30: int, ended: int}
     */
    private function supportEndingBuckets(Collection $productIds): array
    {
        $buckets = [
            180 => 0,
            90 => 0,
            30 => 0,
            'ended' => 0,
        ];

        $periods = ProductSupportPeriod::query()
            ->whereIn('product_id', $productIds)
            ->where('start_basis', SupportPeriodStartBasis::ReleaseDate->value)
            ->with(['versions:id,release_date'])
            ->get()
            ->filter(fn(ProductSupportPeriod $period): bool => $period->scheduleResolved());

        foreach ($periods as $period) {
            $days = $period->daysUntilEnd();

            if ($days === null) {
                continue;
            }

            if ($days < 0) {
                $buckets['ended']++;
            } elseif ($days <= 30) {
                $buckets[30]++;
            } elseif ($days <= 90) {
                $buckets[90]++;
            } elseif ($days <= 180) {
                $buckets[180]++;
            }
        }

        return $buckets;
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function expiredEvidenceCount(Collection $productIds): int
    {
        return Evidence::query()
            ->whereIn('product_id', $productIds)
            ->where('freshness_status', EvidenceFreshnessStatus::Expired->value)
            ->count();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function releasesAwaitingApprovalCount(Collection $productIds): int
    {
        return ProductVersion::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('state', [
                ProductVersionState::SecurityReview->value,
                ProductVersionState::ReleaseCandidate->value,
            ])
            ->count();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return list<array{id: int, title: string, href: string}>
     */
    private function recentCriticalVulnerabilities(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return ProductVulnerability::query()
            ->whereIn('product_id', $productIds)
            ->where('business_severity', VulnerabilityBusinessSeverity::Critical->value)
            ->whereNotIn('status', [
                VulnerabilityStatus::Closed->value,
                VulnerabilityStatus::Rejected->value,
            ])
            ->orderByDesc('id')
            ->limit(self::CRITICAL_VULNERABILITIES_PREVIEW_LIMIT)
            ->get(['id', 'title', 'product_id'])
            ->map(fn(ProductVulnerability $vulnerability): array => [
                'id' => $vulnerability->id,
                'title' => $vulnerability->title,
                'href' => route('products.vulnerabilities.edit', [
                    $vulnerability->product_id,
                    $vulnerability->id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return list<array{id: int, title: string, href: string}>
     */
    private function recentRisks(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return ProductRisk::query()
            ->whereIn('product_id', $productIds)
            ->orderByDesc('id')
            ->limit(self::RECENT_RISKS_LIMIT)
            ->get(['id', 'title', 'product_id'])
            ->map(fn(ProductRisk $risk): array => [
                'id' => $risk->id,
                'title' => $risk->title,
                'href' => route('products.risks.edit', [
                    $risk->product_id,
                    $risk->id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return list<array{id: int, title: string, href: string, product_id: int}>
     */
    private function recentOpenTasks(Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return Task::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(self::OPEN_TASKS_PREVIEW_LIMIT)
            ->get(['id', 'title', 'product_id'])
            ->map(fn(Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'href' => route('products.tasks.edit', [
                    $task->product_id,
                    $task->id,
                ]),
                'product_id' => $task->product_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @param  list<array{id: int, title: string, href: string, product_id: int}>  $previewTasks
     * @return array<string, mixed>|null
     */
    private function openTasksAction(Collection $productIds, array $previewTasks): ?array
    {
        $openTasks = Task::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->count();

        if ($openTasks <= 0) {
            return null;
        }

        $primaryProductId = $previewTasks[0]['product_id'] ?? null;

        return [
            'key' => 'open_tasks',
            'severity' => 'info',
            'title_key' => 'dashboard.actions.open_tasks',
            'count' => $openTasks,
            'href' => $primaryProductId !== null
                ? route('products.tasks.index', $primaryProductId)
                : route('products.index'),
            'items' => array_map(
                static fn(array $task): array => [
                    'id' => $task['id'],
                    'title' => $task['title'],
                    'href' => $task['href'],
                ],
                $previewTasks,
            ),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return array<string, mixed>|null
     */
    private function overdueTasksAction(Collection $productIds): ?array
    {
        $overdueQuery = Task::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now());

        $count = (clone $overdueQuery)->count();

        if ($count <= 0) {
            return null;
        }

        $preview = (clone $overdueQuery)
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(self::OPEN_TASKS_PREVIEW_LIMIT)
            ->get(['id', 'title', 'product_id']);

        $primaryProductId = $preview->first()?->product_id;

        return [
            'key' => 'overdue_tasks',
            'severity' => 'fail',
            'title_key' => 'dashboard.actions.overdue_tasks',
            'count' => $count,
            'href' => $primaryProductId !== null
                ? route('products.tasks.index', $primaryProductId)
                : route('products.index'),
            'items' => $preview
                ->map(fn(Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'href' => route('products.tasks.edit', [
                        $task->product_id,
                        $task->id,
                    ]),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function pendingImportSuggestionCount(Collection $productIds): int
    {
        if ($productIds->isEmpty()) {
            return 0;
        }

        return ImportSuggestion::query()
            ->whereIn('product_id', $productIds)
            ->where('status', ImportSuggestionStatus::Pending)
            ->count();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function failedIntegrationSyncCount(Collection $productIds): int
    {
        if ($productIds->isEmpty()) {
            return 0;
        }

        return ProductIntegrationLink::query()
            ->whereIn('product_id', $productIds)
            ->get(['id', 'last_sync_summary'])
            ->filter(function (ProductIntegrationLink $link): bool {
                $summary = is_array($link->last_sync_summary) ? $link->last_sync_summary : [];
                $error = $summary['error'] ?? null;

                return is_string($error) && $error !== '';
            })
            ->count();
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return array<string, mixed>|null
     */
    private function pendingImportSuggestionsAction(Collection $productIds, int $count): ?array
    {
        if ($count <= 0) {
            return null;
        }

        $firstProductId = ImportSuggestion::query()
            ->whereIn('product_id', $productIds)
            ->where('status', ImportSuggestionStatus::Pending)
            ->orderBy('id')
            ->value('product_id');

        return [
            'key' => 'pending_import_suggestions',
            'severity' => 'warn',
            'title_key' => 'dashboard.actions.pending_import_suggestions',
            'count' => $count,
            'href' => $firstProductId !== null
                ? route('products.edit', $firstProductId)
                : route('products.index'),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     * @return array<string, mixed>|null
     */
    private function failedIntegrationSyncsAction(Collection $productIds, int $count): ?array
    {
        if ($count <= 0) {
            return null;
        }

        $firstProductId = ProductIntegrationLink::query()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'last_sync_summary'])
            ->first(function (ProductIntegrationLink $link): bool {
                $summary = is_array($link->last_sync_summary) ? $link->last_sync_summary : [];
                $error = $summary['error'] ?? null;

                return is_string($error) && $error !== '';
            })
                ?->product_id;

        return [
            'key' => 'failed_integration_syncs',
            'severity' => 'fail',
            'title_key' => 'dashboard.actions.failed_integration_syncs',
            'count' => $count,
            'href' => $firstProductId !== null
                ? route('products.edit', $firstProductId)
                : route('products.index'),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $productIds
     */
    private function overdueReportingCount(Collection $productIds): int
    {
        return ProductVulnerability::query()
            ->whereIn('product_id', $productIds)
            ->whereNotNull('awareness_at')
            ->whereNotIn('status', [
                VulnerabilityStatus::Closed->value,
                VulnerabilityStatus::Rejected->value,
            ])
            ->get()
            ->filter(function (ProductVulnerability $vulnerability): bool {
                $deadline = $vulnerability->deadline72h();

                return $deadline !== null && $deadline->lt(Carbon::now());
            })
            ->count();
    }
}

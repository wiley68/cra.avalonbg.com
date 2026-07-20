<?php

namespace App\Services;

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\ProductVersionState;
use App\Enums\SupportPeriodStartBasis;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityStatus;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductSupportPeriod;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    private const OPEN_TASKS_PREVIEW_LIMIT = 3;

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

        return $this->organizationDashboard($organization);
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
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationDashboard(Organization $organization): array
    {
        /** @var Collection<int, int|string> $productIds */
        $productIds = Product::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $criticalVulns = $this->criticalVulnerabilityCount($productIds);
        $expiredEvidence = $this->expiredEvidenceCount($productIds);
        $overdueReporting = $this->overdueReportingCount($productIds);
        $risksCount = ProductRisk::query()->whereIn('product_id', $productIds)->count();
        $openTasksAction = $this->openTasksAction($productIds);
        $supportBuckets = $this->supportEndingBuckets($productIds);

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
            ],
            'actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function countAction(string $key, string $severity, int $count): ?array
    {
        if ($count <= 0) {
            return null;
        }

        return [
            'key' => $key,
            'severity' => $severity,
            'title_key' => 'dashboard.actions.'.$key,
            'count' => $count,
            'href' => route('products.index'),
        ];
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
            ->filter(fn (ProductSupportPeriod $period): bool => $period->scheduleResolved());

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
     * @return array<string, mixed>|null
     */
    private function openTasksAction(Collection $productIds): ?array
    {
        $openTasksQuery = Task::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ]);

        $openTasks = (clone $openTasksQuery)->count();

        if ($openTasks <= 0) {
            return null;
        }

        $previewTasks = (clone $openTasksQuery)
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(self::OPEN_TASKS_PREVIEW_LIMIT)
            ->get(['id', 'title', 'product_id']);

        $primaryProductId = $previewTasks->first()?->product_id;

        return [
            'key' => 'open_tasks',
            'severity' => 'info',
            'title_key' => 'dashboard.actions.open_tasks',
            'count' => $openTasks,
            'href' => $primaryProductId !== null
                ? route('products.tasks.index', $primaryProductId)
                : route('products.index'),
            'items' => $previewTasks
                ->map(fn (Task $task): array => [
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
                ->map(fn (Task $task): array => [
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

<?php

namespace App\Services;

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\SupportPeriodStartBasis;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityStatus;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductSupportPeriod;
use App\Models\ProductVulnerability;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardService
{
    /**
     * @return array{
     *     mode: string,
     *     organization: array{id: int, name: string, slug: string}|null,
     *     counts: array<string, int>,
     *     actions: list<array{key: string, severity: string, title_key: string, count: int, href: string|null, product_id?: int|null}>
     * }
     */
    public function build(User $user): array
    {
        if ($user->isPlatformAdmin() && $user->currentOrganization() === null) {
            return [
                'mode' => 'platform',
                'organization' => null,
                'counts' => [
                    'organizations' => Organization::query()->count(),
                    'products' => Product::query()->count(),
                ],
                'actions' => [
                    [
                        'key' => 'manage_organizations',
                        'severity' => 'info',
                        'title_key' => 'dashboard.actions.manage_organizations',
                        'count' => Organization::query()->count(),
                        'href' => route('admin.organizations.index'),
                    ],
                ],
            ];
        }

        $organization = $user->currentOrganization();

        if ($organization === null) {
            return [
                'mode' => 'empty',
                'organization' => null,
                'counts' => [],
                'actions' => [],
            ];
        }

        $productIds = Product::query()
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $actions = [];

        $unclassified = Product::query()
            ->where('organization_id', $organization->id)
            ->whereIn('classification_status', [
                ClassificationStatus::Unclassified->value,
                ClassificationStatus::UnderReview->value,
            ])
            ->count();

        if ($unclassified > 0) {
            $actions[] = [
                'key' => 'unclassified_products',
                'severity' => 'warn',
                'title_key' => 'dashboard.actions.unclassified_products',
                'count' => $unclassified,
                'href' => route('products.index'),
            ];
        }

        $withoutSupport = Product::query()
            ->where('organization_id', $organization->id)
            ->whereDoesntHave('supportPeriods')
            ->count();

        if ($withoutSupport > 0) {
            $actions[] = [
                'key' => 'products_without_support',
                'severity' => 'warn',
                'title_key' => 'dashboard.actions.products_without_support',
                'count' => $withoutSupport,
                'href' => route('products.index'),
            ];
        }

        $withoutRisks = Product::query()
            ->where('organization_id', $organization->id)
            ->whereDoesntHave('productRisks')
            ->count();

        if ($withoutRisks > 0) {
            $actions[] = [
                'key' => 'products_without_risks',
                'severity' => 'warn',
                'title_key' => 'dashboard.actions.products_without_risks',
                'count' => $withoutRisks,
                'href' => route('products.index'),
            ];
        }

        $criticalVulns = ProductVulnerability::query()
            ->whereIn('product_id', $productIds)
            ->where('business_severity', VulnerabilityBusinessSeverity::Critical->value)
            ->whereNotIn('status', [
                VulnerabilityStatus::Closed->value,
                VulnerabilityStatus::Rejected->value,
            ])
            ->count();

        if ($criticalVulns > 0) {
            $actions[] = [
                'key' => 'critical_vulnerabilities',
                'severity' => 'fail',
                'title_key' => 'dashboard.actions.critical_vulnerabilities',
                'count' => $criticalVulns,
                'href' => route('products.index'),
            ];
        }

        $endingSupport = ProductSupportPeriod::query()
            ->whereIn('product_id', $productIds)
            ->where('start_basis', SupportPeriodStartBasis::ReleaseDate->value)
            ->with(['versions:id,release_date'])
            ->get()
            ->filter(
                fn(ProductSupportPeriod $period) => $period->isActive() === true
                && ($period->daysUntilEnd() ?? PHP_INT_MAX) <= 90,
            )
            ->count();

        if ($endingSupport > 0) {
            $actions[] = [
                'key' => 'support_ending_soon',
                'severity' => 'warn',
                'title_key' => 'dashboard.actions.support_ending_soon',
                'count' => $endingSupport,
                'href' => route('products.index'),
            ];
        }

        $expiredEvidence = Evidence::query()
            ->whereIn('product_id', $productIds)
            ->where('freshness_status', EvidenceFreshnessStatus::Expired->value)
            ->count();

        if ($expiredEvidence > 0) {
            $actions[] = [
                'key' => 'expired_evidence',
                'severity' => 'fail',
                'title_key' => 'dashboard.actions.expired_evidence',
                'count' => $expiredEvidence,
                'href' => route('products.index'),
            ];
        }

        $openTasks = Task::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->count();

        if ($openTasks > 0) {
            $actions[] = [
                'key' => 'open_tasks',
                'severity' => 'info',
                'title_key' => 'dashboard.actions.open_tasks',
                'count' => $openTasks,
                'href' => route('products.index'),
            ];
        }

        $overdueVulnDeadlines = ProductVulnerability::query()
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

        if ($overdueVulnDeadlines > 0) {
            $actions[] = [
                'key' => 'overdue_reporting',
                'severity' => 'fail',
                'title_key' => 'dashboard.actions.overdue_reporting',
                'count' => $overdueVulnDeadlines,
                'href' => route('products.index'),
            ];
        }

        return [
            'mode' => 'organization',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'counts' => [
                'products' => $productIds->count(),
                'open_tasks' => $openTasks,
                'critical_vulnerabilities' => $criticalVulns,
                'expired_evidence' => $expiredEvidence,
                'risks' => ProductRisk::query()->whereIn('product_id', $productIds)->count(),
            ],
            'actions' => $actions,
        ];
    }
}

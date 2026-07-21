<?php

namespace App\Services;

use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\PatchCampaignStatus;
use App\Enums\PatchCampaignTargetStatus;
use App\Enums\ProductRiskStatus;
use App\Enums\ProductVersionState;
use App\Enums\RequirementApplicabilityStatus;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityStatus;
use App\Models\Evidence;
use App\Models\PatchCampaign;
use App\Models\PatchCampaignTarget;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductControl;
use App\Models\ProductRequirement;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\Sbom;
use App\Models\Task;
use Illuminate\Support\Carbon;

class ProductReadinessService
{
    public function __construct(
        private readonly ProductDeploymentService $deployments,
    ) {
    }

    /**
     * Compact per-module status for product index cards.
     *
     * @return array<string, 'empty'|'complete'|'incomplete'>
     */
    public function cardModuleStatuses(Product $product): array
    {
        $sections = [
            'versions' => $this->versionsSection($product),
            'support_periods' => $this->supportSection($product),
            'requirements' => $this->requirementsSection($product),
            'controls' => $this->controlsSection($product),
            'risks' => $this->risksSection($product),
            'components' => $this->sbomSection($product),
            'vulnerabilities' => $this->vulnerabilitiesSection($product),
            'evidence' => $this->evidenceSection($product),
            'tasks' => $this->tasksSection($product),
        ];

        $statuses = [];
        foreach ($sections as $key => $section) {
            $statuses[$key] = $this->mapSectionToCardStatus($section);
        }

        $aggregate = $this->aggregateCardStatus($statuses);
        $statuses['passport'] = $aggregate;
        $statuses['readiness'] = $aggregate;

        return $statuses;
    }

    /**
     * @param  array{key?: string, status: string, summary: string, metrics?: array<string, mixed>}  $section
     * @return 'empty'|'complete'|'incomplete'
     */
    private function mapSectionToCardStatus(array $section): string
    {
        $summary = $section['summary'] ?? '';
        $status = $section['status'] ?? '';
        $metrics = $section['metrics'] ?? [];

        if (in_array($summary, ['none', 'missing', 'not_available', 'no_active_reporting'], true)) {
            return 'empty';
        }

        if (($section['key'] ?? '') === 'tasks' && (int) ($metrics['total_tasks'] ?? 0) === 0) {
            return 'empty';
        }

        if (($section['key'] ?? '') === 'vulnerabilities' && (int) ($metrics['total'] ?? 0) === 0) {
            return 'empty';
        }

        if ($status === 'pass') {
            return 'complete';
        }

        if ($status === 'na') {
            return 'empty';
        }

        return 'incomplete';
    }

    /**
     * @param  array<string, 'empty'|'complete'|'incomplete'>  $statuses
     * @return 'empty'|'complete'|'incomplete'
     */
    private function aggregateCardStatus(array $statuses): string
    {
        if ($statuses === []) {
            return 'empty';
        }

        $values = array_values($statuses);

        if (!in_array('complete', $values, true) && !in_array('incomplete', $values, true)) {
            return 'empty';
        }

        if (in_array('incomplete', $values, true) || in_array('empty', $values, true)) {
            return 'incomplete';
        }

        return 'complete';
    }

    /**
     * @return array{
     *     generated_at: string,
     *     product: array{id: int, name: string, slug: string},
     *     sections: list<array{key: string, status: string, summary: string, metrics?: array<string, int|float|string|null>}>,
     *     gaps: list<array{section: string, status: string, message_key: string, link: string|null}>,
     *     metrics: array<string, int|float|null>
     * }
     */
    public function build(Product $product): array
    {
        $sections = [
            $this->identificationSection($product),
            $this->classificationSection($product),
            $this->scopeSection($product),
            $this->versionsSection($product),
            $this->supportSection($product),
            $this->requirementsSection($product),
            $this->controlsSection($product),
            $this->risksSection($product),
            $this->sbomSection($product),
            $this->vulnerabilitiesSection($product),
            $this->deploymentsSection($product),
            $this->evidenceSection($product),
            $this->technicalDocumentationSection($product),
            $this->repositorySection($product),
            $this->tasksSection($product),
            $this->responsiblePersonsSection($product),
            $this->releaseSection($product),
            $this->reportingSection($product),
        ];

        $gaps = [];
        foreach ($sections as $section) {
            if (in_array($section['status'], ['warn', 'fail'], true)) {
                $gaps[] = [
                    'section' => $section['key'],
                    'status' => $section['status'],
                    'message_key' => $section['gap_key'] ?? ('products.readiness.gaps.' . $section['key']),
                    'link' => $section['link'] ?? null,
                ];
            }
        }

        $requirements = $this->requirementCounts($product);
        $vulns = $this->vulnerabilityCounts($product);
        $evidence = $this->evidenceCounts($product);

        return [
            'generated_at' => now()->toIso8601String(),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'sections' => array_map(function (array $section): array {
                unset($section['gap_key'], $section['link']);

                return $section;
            }, $sections),
            'gaps' => $gaps,
            'metrics' => [
                'versions_count' => $product->versions()->count(),
                'requirements_total' => $requirements['total'],
                'requirements_assessed_pct' => $requirements['assessed_pct'],
                'requirements_implemented_pct' => $requirements['implemented_pct'],
                'requirements_verified_pct' => $requirements['verified_pct'],
                'controls_count' => ProductControl::query()->where('product_id', $product->id)->count(),
                'risks_count' => ProductRisk::query()->where('product_id', $product->id)->count(),
                'open_vulnerabilities' => $vulns['open'],
                'critical_vulnerabilities' => $vulns['critical'],
                'overdue_vulnerabilities' => $vulns['overdue'],
                'evidence_count' => $evidence['total'],
                'evidence_expired' => $evidence['expired'],
                'components_count' => ProductComponent::query()->where('product_id', $product->id)->count(),
                'sboms_count' => Sbom::query()->where('product_id', $product->id)->count(),
                'open_tasks' => Task::query()
                    ->where('product_id', $product->id)
                    ->whereIn('status', [
                        TaskStatus::Open->value,
                        TaskStatus::InProgress->value,
                        TaskStatus::PendingApproval->value,
                    ])
                    ->count(),
            ],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function identificationSection(Product $product): array
    {
        $hasName = filled($product->name);
        $hasType = $product->product_type !== null;
        $hasManufacturer = filled($product->manufacturer);

        if ($hasName && $hasType && $hasManufacturer) {
            return [
                'key' => 'identification',
                'status' => 'pass',
                'summary' => 'complete',
            ];
        }

        return [
            'key' => 'identification',
            'status' => 'fail',
            'summary' => 'incomplete',
            'gap_key' => 'products.readiness.gaps.identification',
            'link' => 'edit',
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function classificationSection(Product $product): array
    {
        $status = $product->classification_status;

        if ($status === ClassificationStatus::Unclassified) {
            return [
                'key' => 'classification',
                'status' => 'fail',
                'summary' => $status->value,
                'gap_key' => 'products.readiness.gaps.classification',
                'link' => 'edit',
                'metrics' => ['classification_status' => $status->value],
            ];
        }

        if ($status === ClassificationStatus::UnderReview) {
            return [
                'key' => 'classification',
                'status' => 'warn',
                'summary' => $status->value,
                'gap_key' => 'products.readiness.gaps.classification_review',
                'link' => 'edit',
                'metrics' => ['classification_status' => $status->value],
            ];
        }

        return [
            'key' => 'classification',
            'status' => 'pass',
            'summary' => $status->value,
            'metrics' => ['classification_status' => $status->value],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function scopeSection(Product $product): array
    {
        $status = $product->scope_status;

        if ($status === ScopeStatus::InsufficientInformation) {
            return [
                'key' => 'scope',
                'status' => 'fail',
                'summary' => $status->value,
                'gap_key' => 'products.readiness.gaps.scope',
                'link' => 'edit',
                'metrics' => ['scope_status' => $status->value],
            ];
        }

        if ($status === ScopeStatus::FurtherLegalReview) {
            return [
                'key' => 'scope',
                'status' => 'warn',
                'summary' => $status->value,
                'gap_key' => 'products.readiness.gaps.scope_review',
                'link' => 'edit',
                'metrics' => ['scope_status' => $status->value],
            ];
        }

        return [
            'key' => 'scope',
            'status' => 'pass',
            'summary' => $status->value,
            'metrics' => ['scope_status' => $status->value],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function versionsSection(Product $product): array
    {
        $versions = $product->versions()->get(['id', 'state']);
        $count = $versions->count();

        if ($count === 0) {
            return [
                'key' => 'versions',
                'status' => 'fail',
                'summary' => 'none',
                'gap_key' => 'products.readiness.gaps.versions',
                'link' => 'versions',
                'metrics' => ['versions_count' => 0],
            ];
        }

        $nonDraft = $versions->contains(
            fn($version) => $version->state !== ProductVersionState::Draft,
        );

        if (!$nonDraft) {
            return [
                'key' => 'versions',
                'status' => 'warn',
                'summary' => 'draft_only',
                'gap_key' => 'products.readiness.gaps.versions_draft',
                'link' => 'versions',
                'metrics' => ['versions_count' => $count],
            ];
        }

        return [
            'key' => 'versions',
            'status' => 'pass',
            'summary' => 'ok',
            'metrics' => ['versions_count' => $count],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function supportSection(Product $product): array
    {
        $periods = $product->supportPeriods()
            ->with(['versions:id,release_date'])
            ->get();
        $hasStructuredPeriods = $periods->isNotEmpty();
        $hasResolvedSchedule = $periods->contains(
            fn($period) => $period->scheduleResolved(),
        );
        $hasActivePeriod = $periods->contains(
            fn($period) => $period->isActive() === true,
        );
        $hasNotes = filled($product->support_period_notes) || filled($product->end_of_support_policy);
        $versions = $product->versions()->get(['support_status', 'security_support_deadline']);

        $hasSupported = $versions->contains(
            fn($version) => in_array($version->support_status, [
                SupportStatus::Supported,
                SupportStatus::SecurityOnly,
            ], true),
        );

        $unsupportedWithoutDeadline = $versions->contains(
            fn($version) => $version->support_status === SupportStatus::Unsupported
            && $version->security_support_deadline === null,
        );

        $endingSoon = $periods->contains(
            fn($period) => $period->isActive() === true
            && ($period->daysUntilEnd() ?? PHP_INT_MAX) <= 90,
        );
        $endingCritical = $periods->contains(
            fn($period) => $period->scheduleResolved()
            && ($period->daysUntilEnd() ?? PHP_INT_MAX) <= 30,
        );

        if ($hasStructuredPeriods) {
            $calendarProblem = $hasResolvedSchedule && (!$hasActivePeriod || $endingSoon);

            if ($endingCritical) {
                return [
                    'key' => 'support',
                    'status' => 'fail',
                    'summary' => 'ending_critical',
                    'gap_key' => 'products.readiness.gaps.support_ending_critical',
                    'link' => 'support-periods',
                    'metrics' => [
                        'periods_count' => $periods->count(),
                        'active_periods' => $periods->filter(
                            fn($period) => $period->isActive() === true,
                        )->count(),
                    ],
                ];
            }

            if ($calendarProblem || $unsupportedWithoutDeadline) {
                return [
                    'key' => 'support',
                    'status' => 'warn',
                    'summary' => 'partial',
                    'gap_key' => 'products.readiness.gaps.support_partial',
                    'link' => 'support-periods',
                    'metrics' => [
                        'periods_count' => $periods->count(),
                        'active_periods' => $periods->filter(
                            fn($period) => $period->isActive() === true,
                        )->count(),
                    ],
                ];
            }

            return [
                'key' => 'support',
                'status' => 'pass',
                'summary' => 'documented',
                'metrics' => [
                    'periods_count' => $periods->count(),
                    'active_periods' => $periods->filter(
                        fn($period) => $period->isActive() === true,
                    )->count(),
                ],
            ];
        }

        if ($hasNotes || $hasSupported) {
            if ($unsupportedWithoutDeadline) {
                return [
                    'key' => 'support',
                    'status' => 'warn',
                    'summary' => 'partial',
                    'gap_key' => 'products.readiness.gaps.support_partial',
                    'link' => 'support-periods',
                ];
            }

            return [
                'key' => 'support',
                'status' => 'warn',
                'summary' => 'partial',
                'gap_key' => 'products.readiness.gaps.support_periods_missing',
                'link' => 'support-periods',
            ];
        }

        return [
            'key' => 'support',
            'status' => 'fail',
            'summary' => 'missing',
            'gap_key' => 'products.readiness.gaps.support',
            'link' => 'support-periods',
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function requirementsSection(Product $product): array
    {
        $counts = $this->requirementCounts($product);

        if ($counts['total'] === 0) {
            return [
                'key' => 'requirements',
                'status' => 'fail',
                'summary' => 'none',
                'gap_key' => 'products.readiness.gaps.requirements',
                'link' => 'requirements',
                'metrics' => $counts,
            ];
        }

        if ($counts['assessed_pct'] < 100) {
            return [
                'key' => 'requirements',
                'status' => 'warn',
                'summary' => 'partial',
                'gap_key' => 'products.readiness.gaps.requirements_partial',
                'link' => 'requirements',
                'metrics' => $counts,
            ];
        }

        return [
            'key' => 'requirements',
            'status' => 'pass',
            'summary' => 'assessed',
            'metrics' => $counts,
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function controlsSection(Product $product): array
    {
        $count = ProductControl::query()->where('product_id', $product->id)->count();

        if ($count === 0) {
            return [
                'key' => 'controls',
                'status' => 'warn',
                'summary' => 'none',
                'gap_key' => 'products.readiness.gaps.controls',
                'link' => 'controls',
                'metrics' => ['controls_count' => 0],
            ];
        }

        return [
            'key' => 'controls',
            'status' => 'pass',
            'summary' => 'assigned',
            'metrics' => ['controls_count' => $count],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function risksSection(Product $product): array
    {
        $risks = ProductRisk::query()
            ->where('product_id', $product->id)
            ->get(['id', 'status', 'deadline', 'reviewed_at']);

        if ($risks->isEmpty()) {
            return [
                'key' => 'risks',
                'status' => 'fail',
                'summary' => 'none',
                'gap_key' => 'products.readiness.gaps.risks',
                'link' => 'risks',
                'metrics' => ['risks_count' => 0],
            ];
        }

        $now = Carbon::now();
        $openWithoutReview = $risks->contains(
            fn(ProductRisk $risk) => in_array($risk->status, [
                ProductRiskStatus::Open,
                ProductRiskStatus::InTreatment,
            ], true) && $risk->reviewed_at === null,
        );
        $overdue = $risks->contains(
            fn(ProductRisk $risk) => $risk->deadline !== null
            && $risk->deadline->lt($now)
            && $risk->status !== ProductRiskStatus::Closed,
        );

        if ($openWithoutReview || $overdue) {
            return [
                'key' => 'risks',
                'status' => 'warn',
                'summary' => $overdue ? 'overdue' : 'needs_review',
                'gap_key' => $overdue
                    ? 'products.readiness.gaps.risks_overdue'
                    : 'products.readiness.gaps.risks_review',
                'link' => 'risks',
                'metrics' => ['risks_count' => $risks->count()],
            ];
        }

        return [
            'key' => 'risks',
            'status' => 'pass',
            'summary' => 'ok',
            'metrics' => ['risks_count' => $risks->count()],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function sbomSection(Product $product): array
    {
        $sboms = Sbom::query()->where('product_id', $product->id)->count();
        $components = ProductComponent::query()->where('product_id', $product->id)->count();

        if ($sboms === 0 && $components === 0) {
            return [
                'key' => 'sbom',
                'status' => 'fail',
                'summary' => 'none',
                'gap_key' => 'products.readiness.gaps.sbom',
                'link' => 'components',
                'metrics' => ['sboms_count' => 0, 'components_count' => 0],
            ];
        }

        return [
            'key' => 'sbom',
            'status' => 'pass',
            'summary' => 'present',
            'metrics' => ['sboms_count' => $sboms, 'components_count' => $components],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function vulnerabilitiesSection(Product $product): array
    {
        $counts = $this->vulnerabilityCounts($product);

        if ($counts['overdue'] > 0) {
            return [
                'key' => 'vulnerabilities',
                'status' => 'fail',
                'summary' => 'overdue',
                'gap_key' => 'products.readiness.gaps.vulnerabilities_overdue',
                'link' => 'vulnerabilities',
                'metrics' => $counts,
            ];
        }

        if ($counts['critical'] > 0) {
            return [
                'key' => 'vulnerabilities',
                'status' => 'warn',
                'summary' => 'critical_open',
                'gap_key' => 'products.readiness.gaps.vulnerabilities_critical',
                'link' => 'vulnerabilities',
                'metrics' => $counts,
            ];
        }

        return [
            'key' => 'vulnerabilities',
            'status' => 'pass',
            'summary' => $counts['open'] > 0 ? 'open' : 'clear',
            'metrics' => $counts,
        ];
    }

    /**
     * Active patch campaigns with high-criticality targets still unresolved (not updated/excepted).
     *
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function deploymentsSection(Product $product): array
    {
        $activeCampaigns = PatchCampaign::query()
            ->where('product_id', $product->id)
            ->where('status', PatchCampaignStatus::Active)
            ->count();

        $unresolvedHigh = PatchCampaignTarget::query()
            ->whereHas(
                'campaign',
                fn($query) => $query
                    ->where('product_id', $product->id)
                    ->where('status', PatchCampaignStatus::Active),
            )
            ->whereNotIn('status', [
                PatchCampaignTargetStatus::Updated,
                PatchCampaignTargetStatus::Excepted,
            ])
            ->whereHas(
                'deployment.customer',
                fn($query) => $query->where('criticality', CustomerCriticality::High),
            )
            ->count();

        $unsupportedCount = $this->deployments->countOnUnsupportedVersions($product);

        $metrics = [
            'active_campaigns' => $activeCampaigns,
            'unresolved_high_criticality' => $unresolvedHigh,
            'unsupported_installations' => $unsupportedCount,
        ];

        if ($unresolvedHigh > 0) {
            return [
                'key' => 'deployments',
                'status' => 'warn',
                'summary' => 'unresolved_high',
                'gap_key' => 'products.readiness.gaps.unresolved_exposed_deployments',
                'link' => 'campaigns',
                'metrics' => $metrics,
            ];
        }

        if ($unsupportedCount > 0) {
            return [
                'key' => 'deployments',
                'status' => 'warn',
                'summary' => 'unsupported_versions',
                'gap_key' => 'products.readiness.gaps.unsupported_deployments',
                'link' => 'deployments-unsupported',
                'metrics' => $metrics,
            ];
        }

        if ($activeCampaigns > 0) {
            return [
                'key' => 'deployments',
                'status' => 'pass',
                'summary' => 'campaigns_clear',
                'metrics' => $metrics,
            ];
        }

        return [
            'key' => 'deployments',
            'status' => 'pass',
            'summary' => 'no_active_campaign',
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function evidenceSection(Product $product): array
    {
        $counts = $this->evidenceCounts($product);

        if ($counts['total'] === 0) {
            return [
                'key' => 'evidence',
                'status' => 'fail',
                'summary' => 'none',
                'gap_key' => 'products.readiness.gaps.evidence',
                'link' => 'evidence',
                'metrics' => $counts,
            ];
        }

        if ($counts['expired'] > 0 || $counts['invalid'] > 0) {
            return [
                'key' => 'evidence',
                'status' => 'fail',
                'summary' => 'expired',
                'gap_key' => 'products.readiness.gaps.evidence_expired',
                'link' => 'evidence',
                'metrics' => $counts,
            ];
        }

        if ($counts['review_due'] > 0) {
            return [
                'key' => 'evidence',
                'status' => 'warn',
                'summary' => 'review_due',
                'gap_key' => 'products.readiness.gaps.evidence_review',
                'link' => 'evidence',
                'metrics' => $counts,
            ];
        }

        return [
            'key' => 'evidence',
            'status' => 'pass',
            'summary' => 'current',
            'metrics' => $counts,
        ];
    }

    /**
     * Thin technical documentation outline derived from existing module coverage.
     *
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function technicalDocumentationSection(Product $product): array
    {
        $checks = [
            'identification' => filled($product->name)
                && $product->product_type !== null
                && filled($product->manufacturer),
            'risks' => ProductRisk::query()->where('product_id', $product->id)->exists(),
            'sbom' => ProductComponent::query()->where('product_id', $product->id)->exists()
                || Sbom::query()->where('product_id', $product->id)->exists(),
            'support' => $product->supportPeriods()->exists()
                || filled($product->support_period_notes)
                || filled($product->end_of_support_policy),
            'evidence' => Evidence::query()->where('product_id', $product->id)->exists(),
            'versions' => $product->versions()->exists(),
        ];

        $complete = count(array_filter($checks));
        $total = count($checks);
        $metrics = array_merge($checks, [
            'outline_complete' => $complete,
            'outline_total' => $total,
        ]);

        if ($complete === $total) {
            return [
                'key' => 'technical_documentation',
                'status' => 'pass',
                'summary' => 'complete',
                'metrics' => $metrics,
            ];
        }

        if ($complete >= 3) {
            return [
                'key' => 'technical_documentation',
                'status' => 'warn',
                'summary' => 'partial',
                'gap_key' => 'products.readiness.gaps.technical_documentation',
                'link' => 'edit',
                'metrics' => $metrics,
            ];
        }

        return [
            'key' => 'technical_documentation',
            'status' => 'fail',
            'summary' => 'missing',
            'gap_key' => 'products.readiness.gaps.technical_documentation',
            'link' => 'edit',
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function repositorySection(Product $product): array
    {
        $repository = $product->repository;

        if ($repository === null) {
            return [
                'key' => 'repository',
                'status' => 'warn',
                'summary' => 'not_linked',
                'gap_key' => 'products.readiness.gaps.no_repository_linked',
                'link' => 'edit',
                'metrics' => [
                    'linked' => 0,
                    'synced' => 0,
                    'ci_conclusion' => null,
                ],
            ];
        }

        $summary = is_array($repository->last_sync_summary) ? $repository->last_sync_summary : [];
        $ci = is_array($summary['ci'] ?? null) ? $summary['ci'] : [];
        $conclusion = isset($ci['conclusion']) && is_string($ci['conclusion'])
            ? $ci['conclusion']
            : null;

        $failingConclusions = ['failure', 'cancelled', 'timed_out', 'startup_failure', 'action_required'];

        if ($conclusion !== null && in_array($conclusion, $failingConclusions, true)) {
            return [
                'key' => 'repository',
                'status' => 'fail',
                'summary' => 'ci_failing',
                'gap_key' => 'products.readiness.gaps.ci_failing',
                'link' => 'edit',
                'metrics' => [
                    'linked' => 1,
                    'synced' => $repository->last_synced_at !== null ? 1 : 0,
                    'full_name' => $repository->full_name,
                    'ci_conclusion' => $conclusion,
                ],
            ];
        }

        return [
            'key' => 'repository',
            'status' => 'pass',
            'summary' => $repository->last_synced_at !== null ? 'linked_synced' : 'linked',
            'metrics' => [
                'linked' => 1,
                'synced' => $repository->last_synced_at !== null ? 1 : 0,
                'full_name' => $repository->full_name,
                'ci_conclusion' => $conclusion,
            ],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function tasksSection(Product $product): array
    {
        $openStatuses = [
            TaskStatus::Open,
            TaskStatus::InProgress,
            TaskStatus::PendingApproval,
        ];

        $tasks = Task::query()
            ->where('product_id', $product->id)
            ->get(['id', 'status', 'due_at']);

        $open = $tasks->filter(
            fn(Task $task) => in_array($task->status, $openStatuses, true),
        );

        $overdue = $open->contains(
            fn(Task $task) => $task->due_at !== null && $task->due_at->lt(now()),
        );

        $metrics = [
            'open_tasks' => $open->count(),
            'total_tasks' => $tasks->count(),
        ];

        if ($overdue) {
            return [
                'key' => 'tasks',
                'status' => 'warn',
                'summary' => 'overdue',
                'gap_key' => 'products.readiness.gaps.tasks_overdue',
                'link' => 'tasks',
                'metrics' => $metrics,
            ];
        }

        return [
            'key' => 'tasks',
            'status' => 'pass',
            'summary' => $open->isEmpty() ? 'clear' : 'in_progress',
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function responsiblePersonsSection(Product $product): array
    {
        $hasOwner = $product->product_owner_user_id !== null;
        $hasSecurity = $product->security_contact_user_id !== null;

        if ($hasOwner || $hasSecurity) {
            return [
                'key' => 'responsible_persons',
                'status' => 'pass',
                'summary' => $hasOwner && $hasSecurity ? 'both' : 'partial',
            ];
        }

        return [
            'key' => 'responsible_persons',
            'status' => 'warn',
            'summary' => 'missing',
            'gap_key' => 'products.readiness.gaps.responsible_persons',
            'link' => 'edit',
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function releaseSection(Product $product): array
    {
        $awaiting = $product->versions()
            ->whereIn('state', [
                ProductVersionState::SecurityReview->value,
                ProductVersionState::ReleaseCandidate->value,
            ])
            ->count();

        $approvedOrReleased = $product->versions()
            ->whereIn('state', [
                ProductVersionState::Approved->value,
                ProductVersionState::Released->value,
            ])
            ->count();

        if ($awaiting > 0) {
            return [
                'key' => 'release',
                'status' => 'warn',
                'summary' => 'awaiting_approval',
                'gap_key' => 'products.readiness.gaps.release_awaiting',
                'link' => 'versions',
                'metrics' => [
                    'awaiting_approval' => $awaiting,
                    'approved_or_released' => $approvedOrReleased,
                ],
            ];
        }

        if ($approvedOrReleased > 0) {
            return [
                'key' => 'release',
                'status' => 'pass',
                'summary' => 'has_releases',
                'metrics' => [
                    'awaiting_approval' => 0,
                    'approved_or_released' => $approvedOrReleased,
                ],
            ];
        }

        return [
            'key' => 'release',
            'status' => 'na',
            'summary' => 'none',
            'metrics' => [
                'awaiting_approval' => 0,
                'approved_or_released' => 0,
            ],
        ];
    }

    /**
     * @return array{key: string, status: string, summary: string, gap_key?: string, link?: string|null, metrics?: array<string, mixed>}
     */
    private function reportingSection(Product $product): array
    {
        $stats = app(VulnerabilityReportingService::class)->productReportingStats($product);

        if ($stats['overdue_milestones'] > 0) {
            return [
                'key' => 'reporting',
                'status' => 'fail',
                'summary' => 'deadlines_at_risk',
                'gap_key' => 'products.readiness.gaps.reporting',
                'link' => 'vulnerabilities',
                'metrics' => $stats,
            ];
        }

        if ($stats['submitted'] > 0) {
            return [
                'key' => 'reporting',
                'status' => 'pass',
                'summary' => 'submissions_recorded',
                'metrics' => $stats,
            ];
        }

        if ($stats['open_with_awareness'] > 0) {
            return [
                'key' => 'reporting',
                'status' => 'warn',
                'summary' => 'in_progress',
                'gap_key' => 'products.readiness.gaps.reporting',
                'link' => 'vulnerabilities',
                'metrics' => $stats,
            ];
        }

        return [
            'key' => 'reporting',
            'status' => 'na',
            'summary' => 'no_active_reporting',
            'metrics' => $stats,
        ];
    }

    /**
     * @return array{total: int, assessed: int, assessed_pct: float, implemented_pct: float, verified_pct: float}
     */
    private function requirementCounts(Product $product): array
    {
        $rows = ProductRequirement::query()
            ->where('product_id', $product->id)
            ->get(['status']);

        $total = $rows->count();
        if ($total === 0) {
            return [
                'total' => 0,
                'assessed' => 0,
                'assessed_pct' => 0.0,
                'implemented_pct' => 0.0,
                'verified_pct' => 0.0,
            ];
        }

        $assessed = $rows->filter(
            fn(ProductRequirement $row) => $row->status !== RequirementApplicabilityStatus::NotAssessed,
        )->count();

        $implemented = $rows->filter(
            fn(ProductRequirement $row) => in_array($row->status, [
                RequirementApplicabilityStatus::Implemented,
                RequirementApplicabilityStatus::Verified,
                RequirementApplicabilityStatus::ExceptionApproved,
            ], true),
        )->count();

        $verified = $rows->filter(
            fn(ProductRequirement $row) => $row->status === RequirementApplicabilityStatus::Verified,
        )->count();

        return [
            'total' => $total,
            'assessed' => $assessed,
            'assessed_pct' => round(($assessed / $total) * 100, 1),
            'implemented_pct' => round(($implemented / $total) * 100, 1),
            'verified_pct' => round(($verified / $total) * 100, 1),
        ];
    }

    /**
     * @return array{open: int, critical: int, overdue: int, total: int}
     */
    private function vulnerabilityCounts(Product $product): array
    {
        $closed = [
            VulnerabilityStatus::Rejected,
            VulnerabilityStatus::Duplicate,
            VulnerabilityStatus::Patched,
            VulnerabilityStatus::Released,
            VulnerabilityStatus::Closed,
        ];

        $vulns = ProductVulnerability::query()
            ->where('product_id', $product->id)
            ->get(['id', 'status', 'business_severity', 'awareness_at']);

        $open = $vulns->filter(
            fn(ProductVulnerability $vuln) => !in_array($vuln->status, $closed, true),
        );

        $critical = $open->filter(
            fn(ProductVulnerability $vuln) => $vuln->business_severity === VulnerabilityBusinessSeverity::Critical,
        )->count();

        $overdue = $open->filter(function (ProductVulnerability $vuln): bool {
            $d24 = ProductVulnerabilityService::deadline24h($vuln->awareness_at);
            $d72 = ProductVulnerabilityService::deadline72h($vuln->awareness_at);

            return ProductVulnerabilityService::isOverdue($d24)
                || ProductVulnerabilityService::isOverdue($d72);
        })->count();

        return [
            'open' => $open->count(),
            'critical' => $critical,
            'overdue' => $overdue,
            'total' => $vulns->count(),
        ];
    }

    /**
     * @return array{total: int, current: int, review_due: int, expired: int, invalid: int}
     */
    private function evidenceCounts(Product $product): array
    {
        $items = Evidence::query()
            ->where('product_id', $product->id)
            ->get(['freshness_status', 'valid_until', 'review_due_at']);

        $counts = [
            'total' => $items->count(),
            'current' => 0,
            'review_due' => 0,
            'expired' => 0,
            'invalid' => 0,
        ];

        foreach ($items as $item) {
            $freshness = EvidenceService::deriveFreshness(
                $item->freshness_status,
                $item->valid_until,
                $item->review_due_at,
            );

            match ($freshness) {
                EvidenceFreshnessStatus::Current => $counts['current']++,
                EvidenceFreshnessStatus::ReviewDue => $counts['review_due']++,
                EvidenceFreshnessStatus::Expired => $counts['expired']++,
                EvidenceFreshnessStatus::Invalid => $counts['invalid']++,
                default => null,
            };
        }

        return $counts;
    }
}

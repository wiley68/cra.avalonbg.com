<?php

namespace App\Services;

use App\Enums\ClassificationStatus;
use App\Enums\ScopeStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Support\ComplianceWizardSpine;

class ComplianceWizardService
{
    public function __construct(
        private readonly ProductReadinessService $readiness,
    ) {
    }

    /**
     * @return array{
     *     product: array{id: int, name: string, slug: string, product_type: string|null, scope_status: string|null, classification_status: string|null},
     *     steps: list<array{
     *         number: int,
     *         key: string,
     *         required: bool,
     *         label_key: string,
     *         content_key: string,
     *         href: string,
     *         status: 'empty'|'complete'|'attention'|'critical'|'na',
     *         status_reason: array{section: string, summary: string}|null,
     *         is_complete: bool,
     *         is_current: bool
     *     }>,
     *     current_step_key: string|null,
     *     required_complete: bool,
     *     success: bool
     * }
     */
    public function build(Product $product): array
    {
        $moduleDetails = $this->readiness->cardModuleStatusDetails($product);
        $statuses = $this->resolveStepStatuses($product, $moduleDetails);
        $currentKey = $this->resolveCurrentStepKey($statuses);

        $steps = [];
        foreach (ComplianceWizardSpine::steps() as $definition) {
            $key = $definition['key'];
            $status = $statuses[$key];
            $isComplete = $this->isCompleteStatus($status);

            $steps[] = [
                'number' => $definition['number'],
                'key' => $key,
                'required' => $definition['required'],
                'label_key' => $definition['label_key'],
                'content_key' => $definition['content_key'],
                'href' => $this->resolveHref($product, $definition),
                'status' => $status,
                'status_reason' => $this->reasonForKey($product, $key, $status, $moduleDetails),
                'is_complete' => $isComplete,
                'is_current' => $currentKey === $key,
            ];
        }

        $requiredComplete = $this->requiredStepsComplete($statuses);

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'product_type' => $product->product_type?->value,
                'scope_status' => $product->scope_status?->value,
                'classification_status' => $product->classification_status?->value,
            ],
            'steps' => $steps,
            'current_step_key' => $currentKey,
            'required_complete' => $requiredComplete,
            'success' => $requiredComplete,
        ];
    }

    /**
     * @param  array<string, array{status: string, section: string, summary: string}>  $moduleDetails
     * @return array<string, 'empty'|'complete'|'attention'|'critical'|'na'>
     */
    public function resolveStepStatuses(Product $product, array $moduleDetails): array
    {
        $statuses = [];

        foreach (ComplianceWizardSpine::steps() as $definition) {
            $key = $definition['key'];
            $statuses[$key] = $this->statusForKey($product, $key, $moduleDetails);
        }

        return $statuses;
    }

    /**
     * @param  array<string, 'empty'|'complete'|'attention'|'critical'|'na'>  $statuses
     */
    public function resolveCurrentStepKey(array $statuses): ?string
    {
        $definitions = ComplianceWizardSpine::steps();

        foreach ($definitions as $definition) {
            if (!$definition['required']) {
                continue;
            }

            if (!$this->isCompleteStatus($statuses[$definition['key']] ?? 'empty')) {
                return $definition['key'];
            }
        }

        foreach ($definitions as $definition) {
            if ($definition['required']) {
                continue;
            }

            if (!$this->isCompleteStatus($statuses[$definition['key']] ?? 'empty')) {
                return $definition['key'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, 'empty'|'complete'|'attention'|'critical'|'na'>  $statuses
     */
    public function requiredStepsComplete(array $statuses): bool
    {
        foreach (ComplianceWizardSpine::steps() as $definition) {
            if (!$definition['required']) {
                continue;
            }

            if (!$this->isCompleteStatus($statuses[$definition['key']] ?? 'empty')) {
                return false;
            }
        }

        return true;
    }

    public function isCompleteStatus(string $status): bool
    {
        return $status === 'complete' || $status === 'na';
    }

    /**
     * @param  array<string, array{status: string, section: string, summary: string}>  $moduleDetails
     * @return 'empty'|'complete'|'attention'|'critical'|'na'
     */
    private function statusForKey(Product $product, string $key, array $moduleDetails): string
    {
        return match ($key) {
            'product' => $this->productIdentityComplete($product) ? 'complete' : 'empty',
            'scope' => $this->scopeComplete($product) ? 'complete' : 'empty',
            'classification' => $this->classificationComplete($product) ? 'complete' : 'empty',
            'vcs_integrations' => $this->vcsComplete($product) ? 'complete' : 'empty',
            'reporting' => $this->reportingStatus($product),
            'customers' => $this->customersComplete($product) ? 'complete' : 'empty',
            'auditor' => $product->auditorReviewPackages()->exists() ? 'complete' : 'empty',
            default => $this->normalizeModuleStatus($moduleDetails[$key]['status'] ?? 'empty'),
        };
    }

    /**
     * @return 'empty'|'complete'|'attention'|'critical'|'na'
     */
    private function normalizeModuleStatus(string $status): string
    {
        return match ($status) {
            'complete', 'empty', 'attention', 'critical' => $status,
            default => 'empty',
        };
    }

    private function productIdentityComplete(Product $product): bool
    {
        return filled($product->name)
            && filled($product->manufacturer)
            && $product->product_type !== null
            && $product->product_owner_user_id !== null
            && $product->security_contact_user_id !== null;
    }

    private function scopeComplete(Product $product): bool
    {
        $status = $product->scope_status;

        return $status !== null && $status !== ScopeStatus::InsufficientInformation;
    }

    private function classificationComplete(Product $product): bool
    {
        $status = $product->classification_status;

        return $status !== null
            && $status !== ClassificationStatus::Unclassified
            && $status !== ClassificationStatus::UnderReview;
    }

    private function vcsComplete(Product $product): bool
    {
        return $product->repository()->exists() || $product->integrationLinks()->exists();
    }

    private function customersComplete(Product $product): bool
    {
        return Customer::query()
            ->where('organization_id', $product->organization_id)
            ->exists();
    }

    /**
     * @return 'empty'|'complete'|'attention'|'critical'|'na'
     */
    private function reportingStatus(Product $product): string
    {
        $stats = app(VulnerabilityReportingService::class)->productReportingStats($product);

        if (($stats['overdue_milestones'] ?? 0) > 0) {
            return 'critical';
        }

        if (($stats['submitted'] ?? 0) > 0) {
            return 'complete';
        }

        if (($stats['open_with_awareness'] ?? 0) > 0) {
            return 'attention';
        }

        // Nothing to report yet — treated as N/A (counts as complete for spine progress).
        return 'na';
    }

    /**
     * @param  array<string, array{status: string, section: string, summary: string}>  $moduleDetails
     * @param  'empty'|'complete'|'attention'|'critical'|'na'  $status
     * @return array{section: string, summary: string}|null
     */
    private function reasonForKey(
        Product $product,
        string $key,
        string $status,
        array $moduleDetails,
    ): ?array {
        if (isset($moduleDetails[$key])) {
            return [
                'section' => (string) $moduleDetails[$key]['section'],
                'summary' => (string) $moduleDetails[$key]['summary'],
            ];
        }

        return match ($key) {
            'product' => [
                'section' => 'identification',
                'summary' => $status === 'complete' ? 'complete' : 'incomplete',
            ],
            'scope' => [
                'section' => 'scope',
                'summary' => $status === 'complete'
                    ? ($product->scope_status?->value ?? 'likely_in_scope')
                    : 'insufficient_information',
            ],
            'classification' => [
                'section' => 'classification',
                'summary' => $status === 'complete'
                    ? ($product->classification_status?->value ?? 'general')
                    : ($product->classification_status?->value ?? 'unclassified'),
            ],
            'vcs_integrations' => [
                'section' => 'repository',
                'summary' => $status === 'complete' ? 'linked' : 'not_linked',
            ],
            'reporting' => $this->reportingReason($status),
            'customers' => [
                'section' => 'wizard_customers',
                'summary' => $status === 'complete' ? 'present' : 'none',
            ],
            'auditor' => [
                'section' => 'wizard_auditor',
                'summary' => $status === 'complete' ? 'present' : 'none',
            ],
            default => null,
        };
    }

    /**
     * @param  'empty'|'complete'|'attention'|'critical'|'na'  $status
     * @return array{section: string, summary: string}
     */
    private function reportingReason(string $status): array
    {
        return [
            'section' => 'reporting',
            'summary' => match ($status) {
                'critical' => 'deadlines_at_risk',
                'complete' => 'submissions_recorded',
                'attention' => 'in_progress',
                default => 'no_active_reporting',
            },
        ];
    }

    /**
     * @param  array{
     *     number: int,
     *     key: string,
     *     required: bool,
     *     label_key: string,
     *     content_key: string,
     *     href_type: 'product_edit'|'product_route'|'org_route',
     *     route?: string,
     *     hash?: string|null
     * }  $definition
     */
    private function resolveHref(Product $product, array $definition): string
    {
        $hash = isset($definition['hash']) && filled($definition['hash'])
            ? '#' . $definition['hash']
            : '';

        return match ($definition['href_type']) {
            'product_edit' => route('products.edit', $product) . $hash,
            'product_route' => route($definition['route'], $product) . $hash,
            'org_route' => route($definition['route']) . $hash,
        };
    }
}

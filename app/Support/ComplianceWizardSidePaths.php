<?php

namespace App\Support;

/**
 * Allowed side-path edges from Internal Manual Test Plan §6.
 *
 * @phpstan-type SidePathEdge array{
 *     from: string,
 *     to: string,
 *     when_key: string
 * }
 */
final class ComplianceWizardSidePaths
{
    /**
     * Canonical allowed deviations from the linear spine.
     * `from: '*'` means “from any current step” (expanded at build time).
     *
     * @return list<SidePathEdge>
     */
    public static function edges(): array
    {
        return [
            [
                'from' => 'versions',
                'to' => 'sdl',
                'when_key' => 'products.wizard.side_paths.when.release',
            ],
            [
                'from' => 'versions',
                'to' => 'vcs_integrations',
                'when_key' => 'products.wizard.side_paths.when.release',
            ],
            [
                'from' => 'components',
                'to' => 'vulnerabilities',
                'when_key' => 'products.wizard.side_paths.when.finding',
            ],
            [
                'from' => 'evidence',
                'to' => 'tasks',
                'when_key' => 'products.wizard.side_paths.when.continuous',
            ],
            [
                'from' => 'evidence',
                'to' => 'controls',
                'when_key' => 'products.wizard.side_paths.when.continuous',
            ],
            [
                'from' => 'evidence',
                'to' => 'vulnerabilities',
                'when_key' => 'products.wizard.side_paths.when.continuous',
            ],
            [
                'from' => 'vulnerabilities',
                'to' => 'campaigns',
                'when_key' => 'products.wizard.side_paths.when.patch',
            ],
            [
                'from' => 'vulnerabilities',
                'to' => 'deployments',
                'when_key' => 'products.wizard.side_paths.when.patch',
            ],
            [
                'from' => '*',
                'to' => 'incidents',
                'when_key' => 'products.wizard.side_paths.when.incident',
            ],
            [
                'from' => 'passport',
                'to' => 'auditor',
                'when_key' => 'products.wizard.side_paths.when.external_review',
            ],
            [
                'from' => 'readiness',
                'to' => 'auditor',
                'when_key' => 'products.wizard.side_paths.when.external_review',
            ],
        ];
    }
}

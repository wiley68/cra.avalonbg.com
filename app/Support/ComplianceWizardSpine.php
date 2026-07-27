<?php

namespace App\Support;

/**
 * Canonical Compliance Wizard spine (§4.1 Internal Manual Test Plan).
 *
 * @phpstan-type SpineStep array{
 *     number: int,
 *     key: string,
 *     required: bool,
 *     label_key: string,
 *     content_key: string,
 *     href_type: 'product_edit'|'product_route'|'org_route',
 *     route?: string,
 *     hash?: string|null
 * }
 */
final class ComplianceWizardSpine
{
    /**
     * @return list<SpineStep>
     */
    public static function steps(): array
    {
        return [
            [
                'number' => 1,
                'key' => 'product',
                'required' => true,
                'label_key' => 'products.wizard.steps.product.label',
                'content_key' => 'products.wizard.steps.product',
                'href_type' => 'product_edit',
            ],
            [
                'number' => 2,
                'key' => 'scope',
                'required' => true,
                'label_key' => 'products.wizard.steps.scope.label',
                'content_key' => 'products.wizard.steps.scope',
                'href_type' => 'product_edit',
                'hash' => 'scope',
            ],
            [
                'number' => 3,
                'key' => 'classification',
                'required' => true,
                'label_key' => 'products.wizard.steps.classification.label',
                'content_key' => 'products.wizard.steps.classification',
                'href_type' => 'product_edit',
                'hash' => 'classification',
            ],
            [
                'number' => 4,
                'key' => 'versions',
                'required' => true,
                'label_key' => 'products.wizard.steps.versions.label',
                'content_key' => 'products.wizard.steps.versions',
                'href_type' => 'product_route',
                'route' => 'products.versions.index',
            ],
            [
                'number' => 5,
                'key' => 'support_periods',
                'required' => true,
                'label_key' => 'products.wizard.steps.support_periods.label',
                'content_key' => 'products.wizard.steps.support_periods',
                'href_type' => 'product_route',
                'route' => 'products.support-periods.index',
            ],
            [
                'number' => 6,
                'key' => 'vcs_integrations',
                'required' => true,
                'label_key' => 'products.wizard.steps.vcs_integrations.label',
                'content_key' => 'products.wizard.steps.vcs_integrations',
                'href_type' => 'product_edit',
                'hash' => 'integrations',
            ],
            [
                'number' => 7,
                'key' => 'components',
                'required' => true,
                'label_key' => 'products.wizard.steps.components.label',
                'content_key' => 'products.wizard.steps.components',
                'href_type' => 'product_route',
                'route' => 'products.components.index',
            ],
            [
                'number' => 8,
                'key' => 'risks',
                'required' => true,
                'label_key' => 'products.wizard.steps.risks.label',
                'content_key' => 'products.wizard.steps.risks',
                'href_type' => 'product_route',
                'route' => 'products.risks.index',
            ],
            [
                'number' => 9,
                'key' => 'requirements',
                'required' => true,
                'label_key' => 'products.wizard.steps.requirements.label',
                'content_key' => 'products.wizard.steps.requirements',
                'href_type' => 'product_route',
                'route' => 'products.requirements.index',
            ],
            [
                'number' => 10,
                'key' => 'controls',
                'required' => true,
                'label_key' => 'products.wizard.steps.controls.label',
                'content_key' => 'products.wizard.steps.controls',
                'href_type' => 'product_route',
                'route' => 'products.controls.index',
            ],
            [
                'number' => 11,
                'key' => 'evidence',
                'required' => true,
                'label_key' => 'products.wizard.steps.evidence.label',
                'content_key' => 'products.wizard.steps.evidence',
                'href_type' => 'product_route',
                'route' => 'products.evidence.index',
            ],
            [
                'number' => 12,
                'key' => 'tasks',
                'required' => true,
                'label_key' => 'products.wizard.steps.tasks.label',
                'content_key' => 'products.wizard.steps.tasks',
                'href_type' => 'product_route',
                'route' => 'products.tasks.index',
            ],
            [
                'number' => 13,
                'key' => 'vulnerabilities',
                'required' => true,
                'label_key' => 'products.wizard.steps.vulnerabilities.label',
                'content_key' => 'products.wizard.steps.vulnerabilities',
                'href_type' => 'product_route',
                'route' => 'products.vulnerabilities.index',
            ],
            [
                'number' => 14,
                'key' => 'reporting',
                'required' => true,
                'label_key' => 'products.wizard.steps.reporting.label',
                'content_key' => 'products.wizard.steps.reporting',
                'href_type' => 'product_route',
                'route' => 'products.vulnerabilities.index',
            ],
            [
                'number' => 15,
                'key' => 'customers',
                'required' => true,
                'label_key' => 'products.wizard.steps.customers.label',
                'content_key' => 'products.wizard.steps.customers',
                'href_type' => 'org_route',
                'route' => 'customers.index',
            ],
            [
                'number' => 16,
                'key' => 'deployments',
                'required' => true,
                'label_key' => 'products.wizard.steps.deployments.label',
                'content_key' => 'products.wizard.steps.deployments',
                'href_type' => 'product_route',
                'route' => 'products.deployments.index',
            ],
            [
                'number' => 17,
                'key' => 'campaigns',
                'required' => true,
                'label_key' => 'products.wizard.steps.campaigns.label',
                'content_key' => 'products.wizard.steps.campaigns',
                'href_type' => 'product_route',
                'route' => 'products.campaigns.index',
            ],
            [
                'number' => 18,
                'key' => 'incidents',
                'required' => true,
                'label_key' => 'products.wizard.steps.incidents.label',
                'content_key' => 'products.wizard.steps.incidents',
                'href_type' => 'product_route',
                'route' => 'products.incidents.index',
            ],
            [
                'number' => 19,
                'key' => 'sdl',
                'required' => true,
                'label_key' => 'products.wizard.steps.sdl.label',
                'content_key' => 'products.wizard.steps.sdl',
                'href_type' => 'product_route',
                'route' => 'products.sdl.index',
            ],
            [
                'number' => 20,
                'key' => 'security_instructions',
                'required' => true,
                'label_key' => 'products.wizard.steps.security_instructions.label',
                'content_key' => 'products.wizard.steps.security_instructions',
                'href_type' => 'product_route',
                'route' => 'products.security-instructions.index',
            ],
            [
                'number' => 21,
                'key' => 'technical_documentation',
                'required' => true,
                'label_key' => 'products.wizard.steps.technical_documentation.label',
                'content_key' => 'products.wizard.steps.technical_documentation',
                'href_type' => 'product_route',
                'route' => 'products.technical-documentation.index',
            ],
            [
                'number' => 22,
                'key' => 'passport',
                'required' => true,
                'label_key' => 'products.wizard.steps.passport.label',
                'content_key' => 'products.wizard.steps.passport',
                'href_type' => 'product_route',
                'route' => 'products.passport.show',
            ],
            [
                'number' => 23,
                'key' => 'readiness',
                'required' => true,
                'label_key' => 'products.wizard.steps.readiness.label',
                'content_key' => 'products.wizard.steps.readiness',
                'href_type' => 'product_route',
                'route' => 'products.readiness.show',
            ],
            [
                'number' => 24,
                'key' => 'auditor',
                'required' => false,
                'label_key' => 'products.wizard.steps.auditor.label',
                'content_key' => 'products.wizard.steps.auditor',
                'href_type' => 'org_route',
                'route' => 'auditor.index',
            ],
            [
                'number' => 25,
                'key' => 'assistant',
                'required' => false,
                'label_key' => 'products.wizard.steps.assistant.label',
                'content_key' => 'products.wizard.steps.assistant',
                'href_type' => 'product_route',
                'route' => 'products.assistant.show',
            ],
        ];
    }

    /**
     * Optional spine keys that may be dismissed (24–25).
     *
     * @return list<string>
     */
    public static function optionalKeys(): array
    {
        return array_values(array_map(
            static fn (array $step): string => $step['key'],
            array_filter(
                self::steps(),
                static fn (array $step): bool => ! $step['required'],
            ),
        ));
    }

    public static function isOptionalKey(string $key): bool
    {
        return in_array($key, self::optionalKeys(), true);
    }
}

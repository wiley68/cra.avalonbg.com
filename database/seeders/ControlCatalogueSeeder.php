<?php

namespace Database\Seeders;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Requirement;
use Illuminate\Database\Seeder;

class ControlCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()->each(function (Organization $organization): void {
            $this->seedForOrganization($organization);
        });
    }

    public function seedForOrganization(Organization $organization): void
    {
        $requirementIdsByCode = Requirement::query()
            ->whereIn('code', $this->allLinkedRequirementCodes())
            ->pluck('id', 'code');

        foreach ($this->starterControls() as $item) {
            $control = Control::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $item['code'],
                ],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'implementation_guidance' => $item['implementation_guidance'],
                    'automation_level' => $item['automation_level'],
                    'frequency' => $item['frequency'],
                    'is_active' => true,
                ],
            );

            $ids = collect($item['requirement_codes'])
                ->map(fn(string $code) => $requirementIdsByCode->get($code))
                ->filter()
                ->values()
                ->all();

            $control->requirements()->sync($ids);
        }
    }

    /**
     * @return list<string>
     */
    private function allLinkedRequirementCodes(): array
    {
        return collect($this->starterControls())
            ->flatMap(fn(array $item) => $item['requirement_codes'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     implementation_guidance: string,
     *     automation_level: ControlAutomationLevel,
     *     frequency: ControlFrequency,
     *     requirement_codes: list<string>
     * }>
     */
    private function starterControls(): array
    {
        return [
            [
                'code' => 'CTL-DEP-SCAN',
                'name' => 'Dependency scanning before release',
                'description' => 'Scan product dependencies for known vulnerabilities before each production release.',
                'implementation_guidance' => 'Run SCA in CI on every release candidate; block critical findings unless accepted-risk is recorded.',
                'automation_level' => ControlAutomationLevel::Automated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-02', 'CRA-AI-15', 'CRA-AI-16'],
            ],
            [
                'code' => 'CTL-PEER-REVIEW',
                'name' => 'Mandatory peer review',
                'description' => 'Require peer review before merging security-relevant changes.',
                'implementation_guidance' => 'Enforce branch protection with at least one approving review on protected branches.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-01', 'CRA-AI-10'],
            ],
            [
                'code' => 'CTL-SECRETS-SCAN',
                'name' => 'Secrets scanning',
                'description' => 'Detect secrets in source, CI logs and artifacts.',
                'implementation_guidance' => 'Enable secret scanning on push and in CI; rotate any exposed credentials immediately.',
                'automation_level' => ControlAutomationLevel::Automated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-05', 'CRA-AI-04'],
            ],
            [
                'code' => 'CTL-SIGNED-RELEASE',
                'name' => 'Signed release packages',
                'description' => 'Sign release artifacts and publish verifiable checksums.',
                'implementation_guidance' => 'Sign packages with a controlled signing key; publish hashes alongside downloads.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-06', 'CRA-AI-13'],
            ],
            [
                'code' => 'CTL-SECURE-UPDATE',
                'name' => 'Secure update mechanism',
                'description' => 'Deliver updates over a secure channel with integrity verification.',
                'implementation_guidance' => 'Use HTTPS/signed update payloads; document rollback for failed updates.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-13', 'CRA-AI-19'],
            ],
            [
                'code' => 'CTL-VULN-DISCLOSURE',
                'name' => 'Vulnerability disclosure channel',
                'description' => 'Public coordinated vulnerability disclosure contact and process.',
                'implementation_guidance' => 'Publish security@ contact and CVD policy; acknowledge reports within defined SLA.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-12', 'CRA-AI-18'],
            ],
            [
                'code' => 'CTL-SUPPORTED-VERSIONS',
                'name' => 'Supported-version inventory',
                'description' => 'Maintain inventory of supported product versions during the support period.',
                'implementation_guidance' => 'Keep a living list of supported versions and communicate EOS dates to customers.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-19', 'CRA-AI-16'],
            ],
            [
                'code' => 'CTL-SEC-REGRESSION',
                'name' => 'Security regression testing',
                'description' => 'Run security-focused regression tests before release.',
                'implementation_guidance' => 'Include auth, input validation and update-path tests in the release gate.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-01', 'CRA-AI-04', 'CRA-AI-08'],
            ],
            [
                'code' => 'CTL-BACKUP-RESTORE',
                'name' => 'Backup and restore testing',
                'description' => 'Periodically test backup and restore for critical product data/services.',
                'implementation_guidance' => 'Schedule restore drills; record RTO/RPO outcomes.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-08'],
            ],
            [
                'code' => 'CTL-NO-DEFAULT-CREDS',
                'name' => 'Prohibition of default credentials',
                'description' => 'Do not ship default or shared credentials.',
                'implementation_guidance' => 'Force unique credentials or first-login setup; include check in release checklist.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-03', 'CRA-AI-04'],
            ],
            [
                'code' => 'CTL-RELEASE-APPROVAL',
                'name' => 'Release approval workflow',
                'description' => 'Require documented approval before production release.',
                'implementation_guidance' => 'Capture approver, checklist results and security gate outcomes in the release record.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-02', 'CRA-AI-16'],
            ],
            [
                'code' => 'CTL-INCIDENT-ESC',
                'name' => 'Incident escalation',
                'description' => 'Defined escalation path for security incidents affecting the product.',
                'implementation_guidance' => 'Document severity levels, contacts and notification steps; rehearse annually.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::OnDemand,
                'requirement_codes' => ['CRA-AI-11', 'CRA-AI-12'],
            ],
            [
                'code' => 'CTL-KEY-ROTATION',
                'name' => 'Cryptographic key rotation',
                'description' => 'Rotate signing and encryption keys on a defined schedule and after incidents.',
                'implementation_guidance' => 'Inventory keys; set rotation cadence; record custody and rotation events.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-05', 'CRA-AI-06', 'CRA-AI-13'],
            ],
            [
                'code' => 'CTL-SECURE-LOGGING',
                'name' => 'Secure logging',
                'description' => 'Log security-relevant events without exposing sensitive data.',
                'implementation_guidance' => 'Define event catalogue, retention and masking rules; verify in staging.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-20', 'CRA-AI-05'],
            ],
            [
                'code' => 'CTL-INPUT-VALIDATION',
                'name' => 'Input validation',
                'description' => 'Validate and sanitise untrusted input at trust boundaries.',
                'implementation_guidance' => 'Centralise validation helpers; cover APIs and forms with negative tests.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-06', 'CRA-AI-10'],
            ],
            [
                'code' => 'CTL-ACCESS-REVIEW',
                'name' => 'Access-control review',
                'description' => 'Periodically review product and admin access rights.',
                'implementation_guidance' => 'Review roles and privileged accounts on a schedule; remove unused access.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-04'],
            ],
        ];
    }
}

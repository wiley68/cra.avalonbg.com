<?php

namespace Database\Seeders;

use App\Models\Regulation;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use Illuminate\Database\Seeder;

class RequirementCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $regulation = Regulation::query()->updateOrCreate(
            ['code' => 'CRA-2024-2847'],
            [
                'title' => 'Cyber Resilience Act — Regulation (EU) 2024/2847',
                'jurisdiction' => 'EU',
            ],
        );

        $items = $this->starterRequirements();

        foreach ($items as $index => $item) {
            $requirement = Requirement::query()->updateOrCreate(
                [
                    'regulation_id' => $regulation->id,
                    'code' => $item['code'],
                ],
                [
                    'article_ref' => $item['article_ref'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );

            $existingCurrent = RequirementVersion::query()
                ->where('requirement_id', $requirement->id)
                ->where('is_current', true)
                ->first();

            if ($existingCurrent !== null) {
                $existingCurrent->update([
                    'requirement_text' => $item['requirement_text'],
                    'plain_language' => $item['plain_language'],
                    'applicability_notes' => $item['applicability_notes'],
                    'suggested_controls_text' => $item['suggested_controls_text'],
                    'required_evidence_text' => $item['required_evidence_text'],
                ]);

                continue;
            }

            RequirementVersion::query()->create([
                'requirement_id' => $requirement->id,
                'version' => 1,
                'requirement_text' => $item['requirement_text'],
                'plain_language' => $item['plain_language'],
                'applicability_notes' => $item['applicability_notes'],
                'suggested_controls_text' => $item['suggested_controls_text'],
                'required_evidence_text' => $item['required_evidence_text'],
                'published_at' => now(),
                'is_current' => true,
            ]);
        }
    }

    /**
     * Curated starter set (not a full legal corpus).
     *
     * @return list<array{
     *     code: string,
     *     article_ref: string,
     *     requirement_text: string,
     *     plain_language: string,
     *     applicability_notes: string,
     *     suggested_controls_text: string,
     *     required_evidence_text: string
     * }>
     */
    private function starterRequirements(): array
    {
        return [
            [
                'code' => 'CRA-AI-01',
                'article_ref' => 'Annex I Part I (1)',
                'requirement_text' => 'Products with digital elements shall be designed, developed and produced in such a way that they ensure an appropriate level of cybersecurity based on the risks.',
                'plain_language' => 'Design and build the product with cybersecurity proportionate to its risks.',
                'applicability_notes' => 'Applies to products with digital elements placed on the EU market.',
                'suggested_controls_text' => "Secure design reviews\nThreat modelling\nSecurity requirements in backlog",
                'required_evidence_text' => "Threat model\nSecurity design review records\nRisk assessment summary",
            ],
            [
                'code' => 'CRA-AI-02',
                'article_ref' => 'Annex I Part I (2)',
                'requirement_text' => 'Products with digital elements shall be delivered without any known exploitable vulnerabilities.',
                'plain_language' => 'Do not ship with known exploitable vulnerabilities.',
                'applicability_notes' => 'Applies at the time of placing on the market / making available.',
                'suggested_controls_text' => "Dependency scanning before release\nVulnerability triage gate\nAccepted-risk decision workflow",
                'required_evidence_text' => "SBOM\nDependency scan report\nSecurity review / accepted-risk decision",
            ],
            [
                'code' => 'CRA-AI-03',
                'article_ref' => 'Annex I Part I (2)(a)',
                'requirement_text' => 'Products with digital elements shall be based on an appropriate level of security by default configuration.',
                'plain_language' => 'Ship secure defaults; avoid insecure out-of-the-box settings.',
                'applicability_notes' => 'Especially relevant for installable software and appliances.',
                'suggested_controls_text' => "Secure default configuration checklist\nProhibit default credentials\nHardening guide for operators",
                'required_evidence_text' => "Default config baseline\nHardening documentation\nRelease checklist",
            ],
            [
                'code' => 'CRA-AI-04',
                'article_ref' => 'Annex I Part I (2)(b)',
                'requirement_text' => 'Products with digital elements shall ensure protection from unauthorised access by appropriate control mechanisms.',
                'plain_language' => 'Protect against unauthorised access with suitable authentication and access controls.',
                'applicability_notes' => 'Applies where the product exposes interfaces or accounts.',
                'suggested_controls_text' => "Authentication controls\nAccess-control reviews\nLeast-privilege defaults",
                'required_evidence_text' => "Access-control design\nAuth test results\nRole matrix",
            ],
            [
                'code' => 'CRA-AI-05',
                'article_ref' => 'Annex I Part I (2)(c)',
                'requirement_text' => 'Products with digital elements shall protect the confidentiality of stored, transmitted or otherwise processed data.',
                'plain_language' => 'Protect confidentiality of data at rest and in transit.',
                'applicability_notes' => 'Applies where personal or sensitive product data is processed.',
                'suggested_controls_text' => "TLS for network traffic\nEncryption at rest where needed\nSecrets management",
                'required_evidence_text' => "Crypto inventory\nTLS configuration evidence\nSecrets handling policy",
            ],
            [
                'code' => 'CRA-AI-06',
                'article_ref' => 'Annex I Part I (2)(d)',
                'requirement_text' => 'Products with digital elements shall protect the integrity of stored, transmitted or otherwise processed data.',
                'plain_language' => 'Protect integrity of data and software packages.',
                'applicability_notes' => 'Applies to configuration, updates and processed data.',
                'suggested_controls_text' => "Signed release packages\nChecksum verification\nInput validation",
                'required_evidence_text' => "Signing process docs\nArtifact hashes\nIntegrity test records",
            ],
            [
                'code' => 'CRA-AI-07',
                'article_ref' => 'Annex I Part I (2)(e)',
                'requirement_text' => 'Products with digital elements shall process only data that are adequate, relevant and limited to what is necessary.',
                'plain_language' => 'Minimise data collection and processing to what the product needs.',
                'applicability_notes' => 'Aligns with data minimisation good practice.',
                'suggested_controls_text' => "Data inventory\nPurpose limitation review\nRetention limits",
                'required_evidence_text' => "Data flow diagram\nRetention policy\nPrivacy review notes",
            ],
            [
                'code' => 'CRA-AI-08',
                'article_ref' => 'Annex I Part I (2)(f)',
                'requirement_text' => 'Products with digital elements shall protect the availability of essential functions.',
                'plain_language' => 'Keep essential functions available under expected conditions and attacks.',
                'applicability_notes' => 'Critical for products supporting essential operations.',
                'suggested_controls_text' => "Availability/resilience testing\nRate limiting\nBackup and restore testing",
                'required_evidence_text' => "Availability test reports\nRestore drill records\nIncident response runbooks",
            ],
            [
                'code' => 'CRA-AI-09',
                'article_ref' => 'Annex I Part I (2)(g)',
                'requirement_text' => 'Products with digital elements shall minimise their negative impact on the availability of services provided by other devices or networks.',
                'plain_language' => 'Avoid designs that harm availability of other devices or networks.',
                'applicability_notes' => 'Relevant for networked products and plugins.',
                'suggested_controls_text' => "Network behaviour review\nResource usage limits\nCompatibility testing",
                'required_evidence_text' => "Network impact assessment\nLoad test notes",
            ],
            [
                'code' => 'CRA-AI-10',
                'article_ref' => 'Annex I Part I (2)(h)',
                'requirement_text' => 'Products with digital elements shall be designed, developed and produced to limit attack surfaces.',
                'plain_language' => 'Reduce attack surface: disable unused interfaces and features by default.',
                'applicability_notes' => 'Applies broadly to software and devices with interfaces.',
                'suggested_controls_text' => "Attack-surface inventory\nFeature flags / disable unused services\nPort exposure review",
                'required_evidence_text' => "Attack-surface inventory\nHardening checklist",
            ],
            [
                'code' => 'CRA-AI-11',
                'article_ref' => 'Annex I Part I (2)(i)',
                'requirement_text' => 'Products with digital elements shall be designed to reduce the impact of an incident through appropriate exploitation mitigation mechanisms.',
                'plain_language' => 'Include mitigations that limit damage if a vulnerability is exploited.',
                'applicability_notes' => 'E.g. sandboxing, privilege separation, fail-safe behaviour.',
                'suggested_controls_text' => "Privilege separation\nSandboxing / process isolation\nFail-safe defaults",
                'required_evidence_text' => "Architecture decision records\nMitigation test results",
            ],
            [
                'code' => 'CRA-AI-12',
                'article_ref' => 'Annex I Part I (2)(j)',
                'requirement_text' => 'Products with digital elements shall provide security related information through exploitation of vulnerabilities and coordinated vulnerability disclosure.',
                'plain_language' => 'Support coordinated vulnerability disclosure and share security-relevant information.',
                'applicability_notes' => 'Manufacturer disclosure channel and process.',
                'suggested_controls_text' => "Public vulnerability disclosure policy\nSecurity contact\nTriage SLA",
                'required_evidence_text' => "Vulnerability disclosure policy\nSecurity contact page\nTriage records",
            ],
            [
                'code' => 'CRA-AI-13',
                'article_ref' => 'Annex I Part I (2)(k)',
                'requirement_text' => 'Products with digital elements shall provide for secure and, where relevant, automatic updates and secure delivery of updates.',
                'plain_language' => 'Provide a secure update mechanism for the support period.',
                'applicability_notes' => 'Applies where the product can receive updates.',
                'suggested_controls_text' => "Signed updates\nSecure update channel\nUpdate rollback strategy",
                'required_evidence_text' => "Update architecture\nSigning keys custody\nUpdate test evidence",
            ],
            [
                'code' => 'CRA-AI-14',
                'article_ref' => 'Annex I Part I (2)(l)',
                'requirement_text' => 'Products with digital elements shall be designed, developed and produced to securely reset to factory default state while preserving security settings where appropriate.',
                'plain_language' => 'Support secure factory reset without leaving the product insecure.',
                'applicability_notes' => 'More relevant for devices/appliances; assess for software products case by case.',
                'suggested_controls_text' => "Documented reset procedure\nPreserve essential security settings\nPost-reset hardening",
                'required_evidence_text' => "Reset procedure docs\nTest of reset behaviour",
            ],
            [
                'code' => 'CRA-AI-15',
                'article_ref' => 'Annex I Part II (1)',
                'requirement_text' => 'Manufacturers shall identify and document vulnerabilities and components contained in the product, including by drawing up an SBOM.',
                'plain_language' => 'Maintain an SBOM and track vulnerabilities in product components.',
                'applicability_notes' => 'Core manufacturer obligation for products with digital elements.',
                'suggested_controls_text' => "SBOM generation in CI\nComponent inventory\nVulnerability matching workflow",
                'required_evidence_text' => "SBOM (CycloneDX/SPDX)\nComponent inventory\nVulnerability register entries",
            ],
            [
                'code' => 'CRA-AI-16',
                'article_ref' => 'Annex I Part II (2)',
                'requirement_text' => 'Manufacturers shall address and remediate vulnerabilities without delay, including by providing security updates.',
                'plain_language' => 'Remediate vulnerabilities promptly and ship security updates.',
                'applicability_notes' => 'Tied to support period and severity.',
                'suggested_controls_text' => "Vulnerability SLA\nPatch release process\nSecurity-only hotfix path",
                'required_evidence_text' => "Remediation tickets\nSecurity release notes\nSLA metrics",
            ],
            [
                'code' => 'CRA-AI-17',
                'article_ref' => 'Annex I Part II (5)',
                'requirement_text' => 'Manufacturers shall provide information relating to cybersecurity of the product and how to securely install, configure and operate it.',
                'plain_language' => 'Publish clear security installation, configuration and operation guidance.',
                'applicability_notes' => 'User documentation obligation.',
                'suggested_controls_text' => "Security hardening guide\nInstall/config docs review\nRelease notes for security settings",
                'required_evidence_text' => "User security documentation\nDoc review checklist",
            ],
            [
                'code' => 'CRA-AI-18',
                'article_ref' => 'Annex I Part II (6)',
                'requirement_text' => 'Manufacturers shall provide for coordinated vulnerability disclosure policies and processes.',
                'plain_language' => 'Have a documented coordinated vulnerability disclosure process.',
                'applicability_notes' => 'Complements Annex I Part I (2)(j).',
                'suggested_controls_text' => "CVD policy published\nIntake mailbox / form\nAcknowledgement timelines",
                'required_evidence_text' => "Published CVD policy\nIntake logs\nProcess SOP",
            ],
            [
                'code' => 'CRA-AI-19',
                'article_ref' => 'Art. 13 / support period',
                'requirement_text' => 'Manufacturers shall ensure that vulnerabilities are handled effectively and that security updates are available during the support period.',
                'plain_language' => 'Define and honour a support period with security updates.',
                'applicability_notes' => 'Link to product support period policy.',
                'suggested_controls_text' => "Documented support period\nSupported-version inventory\nEOS communication plan",
                'required_evidence_text' => "Support period statement\nSupported versions list\nCustomer notifications",
            ],
            [
                'code' => 'CRA-AI-20',
                'article_ref' => 'Annex I Part II (logging)',
                'requirement_text' => 'Products with digital elements shall, where appropriate, record and monitor relevant internal activity and enable security event logging.',
                'plain_language' => 'Provide appropriate security logging and monitoring capabilities.',
                'applicability_notes' => 'Assess appropriateness based on product type and risk.',
                'suggested_controls_text' => "Security event logging\nLog integrity / retention guidance\nAlerting for critical events",
                'required_evidence_text' => "Logging design\nSample log events\nRetention policy",
            ],
        ];
    }
}

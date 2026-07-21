<?php

namespace App\Support;

use App\Enums\PolicyType;

/**
 * Suggested org policy types for controls / CRA requirement codes.
 */
final class RelatedPolicyTypes
{
    /**
     * @var array<string, list<PolicyType>>
     */
    private const CONTROL_MAP = [
        'CTL-DEP-SCAN' => [PolicyType::ThirdPartyComponents],
        'CTL-PEER-REVIEW' => [PolicyType::SecureDevelopment],
        'CTL-SECRETS-SCAN' => [PolicyType::SecureDevelopment],
        'CTL-SIGNED-RELEASE' => [PolicyType::Update, PolicyType::SecureDevelopment],
        'CTL-SECURE-UPDATE' => [PolicyType::Update],
        'CTL-VULN-DISCLOSURE' => [PolicyType::VulnerabilityDisclosure],
        'CTL-SUPPORTED-VERSIONS' => [PolicyType::Support],
        'CTL-SEC-REGRESSION' => [PolicyType::SecureDevelopment],
        'CTL-BACKUP-RESTORE' => [PolicyType::IncidentResponse],
        'CTL-NO-DEFAULT-CREDS' => [PolicyType::SecureDevelopment],
        'CTL-RELEASE-APPROVAL' => [PolicyType::SecureDevelopment],
        'CTL-INCIDENT-ESC' => [PolicyType::IncidentResponse],
        'CTL-KEY-ROTATION' => [PolicyType::SecureDevelopment, PolicyType::Update],
        'CTL-SECURE-LOGGING' => [PolicyType::SecureDevelopment, PolicyType::IncidentResponse],
        'CTL-INPUT-VALIDATION' => [PolicyType::SecureDevelopment],
        'CTL-ACCESS-REVIEW' => [PolicyType::SecureDevelopment],
    ];

    /**
     * @var array<string, list<PolicyType>>
     */
    private const REQUIREMENT_MAP = [
        'CRA-AI-01' => [PolicyType::SecureDevelopment],
        'CRA-AI-02' => [PolicyType::SecureDevelopment, PolicyType::ThirdPartyComponents],
        'CRA-AI-03' => [PolicyType::SecureDevelopment],
        'CRA-AI-04' => [PolicyType::SecureDevelopment],
        'CRA-AI-05' => [PolicyType::SecureDevelopment],
        'CRA-AI-06' => [PolicyType::SecureDevelopment, PolicyType::Update],
        'CRA-AI-08' => [PolicyType::SecureDevelopment, PolicyType::IncidentResponse],
        'CRA-AI-10' => [PolicyType::SecureDevelopment],
        'CRA-AI-11' => [PolicyType::IncidentResponse],
        'CRA-AI-12' => [PolicyType::VulnerabilityDisclosure, PolicyType::IncidentResponse],
        'CRA-AI-13' => [PolicyType::Update],
        'CRA-AI-15' => [PolicyType::ThirdPartyComponents],
        'CRA-AI-16' => [PolicyType::ThirdPartyComponents, PolicyType::Support],
        'CRA-AI-18' => [PolicyType::VulnerabilityDisclosure],
        'CRA-AI-19' => [PolicyType::Support, PolicyType::Update],
        'CRA-AI-20' => [PolicyType::SecureDevelopment, PolicyType::IncidentResponse],
    ];

    /**
     * @param  list<string>  $requirementCodes
     * @return list<string>
     */
    public static function forControl(string $controlCode, array $requirementCodes = []): array
    {
        $types = self::CONTROL_MAP[$controlCode] ?? [];

        foreach ($requirementCodes as $code) {
            $types = [...$types, ...self::typesForRequirementCode($code)];
        }

        return self::uniqueValues($types);
    }

    /**
     * @return list<string>
     */
    public static function forRequirement(string $requirementCode): array
    {
        return self::uniqueValues(self::typesForRequirementCode($requirementCode));
    }

    /**
     * @return list<PolicyType>
     */
    private static function typesForRequirementCode(string $requirementCode): array
    {
        return self::REQUIREMENT_MAP[$requirementCode] ?? [];
    }

    /**
     * @param  list<PolicyType>  $types
     * @return list<string>
     */
    private static function uniqueValues(array $types): array
    {
        $values = [];

        foreach ($types as $type) {
            $values[$type->value] = $type->value;
        }

        $ordered = [];
        foreach (PolicyType::cases() as $case) {
            if (isset($values[$case->value])) {
                $ordered[] = $case->value;
            }
        }

        return $ordered;
    }
}

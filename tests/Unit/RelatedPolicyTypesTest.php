<?php

use App\Enums\PolicyType;
use App\Support\RelatedPolicyTypes;

test('related policy types map vulnerability disclosure control', function () {
    expect(RelatedPolicyTypes::forControl('CTL-VULN-DISCLOSURE'))
        ->toBe([PolicyType::VulnerabilityDisclosure->value]);
});

test('related policy types map requirement codes', function () {
    expect(RelatedPolicyTypes::forRequirement('CRA-AI-19'))
        ->toBe([
            PolicyType::Support->value,
            PolicyType::Update->value,
        ]);
});

test('related policy types merge control and requirement hints uniquely', function () {
    expect(RelatedPolicyTypes::forControl('CTL-SECURE-UPDATE', ['CRA-AI-12']))
        ->toBe([
            PolicyType::VulnerabilityDisclosure->value,
            PolicyType::Update->value,
            PolicyType::IncidentResponse->value,
        ]);
});

test('unknown codes yield empty related policy types', function () {
    expect(RelatedPolicyTypes::forControl('CTL-UNKNOWN'))->toBe([])
        ->and(RelatedPolicyTypes::forRequirement('CRA-UNKNOWN'))->toBe([]);
});

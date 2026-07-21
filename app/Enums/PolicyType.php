<?php

namespace App\Enums;

enum PolicyType: string
{
    case VulnerabilityDisclosure = 'vulnerability_disclosure';
    case SecureDevelopment = 'secure_development';
    case Support = 'support';
    case Update = 'update';
    case IncidentResponse = 'incident_response';
    case ThirdPartyComponents = 'third_party_components';
}

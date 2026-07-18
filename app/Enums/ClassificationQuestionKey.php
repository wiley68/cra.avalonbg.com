<?php

namespace App\Enums;

enum ClassificationQuestionKey: string
{
    case IdentityAccessSecurity = 'identity_access_security';
    case NetworkSecurity = 'network_security';
    case EndpointSecurity = 'endpoint_security';
    case BrowserOrRuntime = 'browser_or_runtime';
    case OperatingSystem = 'operating_system';
    case HypervisorContainers = 'hypervisor_containers';
    case PkiCrypto = 'pki_crypto';
    case CriticalInfrastructure = 'critical_infrastructure';
    case SectorSpecificRegime = 'sector_specific_regime';
    case ExplicitlyExcluded = 'explicitly_excluded';

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}

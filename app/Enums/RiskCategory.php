<?php

namespace App\Enums;

enum RiskCategory: string
{
    case UnauthorisedAccess = 'unauthorised_access';
    case PrivilegeEscalation = 'privilege_escalation';
    case DataExposure = 'data_exposure';
    case InsecureCommunication = 'insecure_communication';
    case BrokenAuthentication = 'broken_authentication';
    case Injection = 'injection';
    case DependencyCompromise = 'dependency_compromise';
    case UpdateMechanismCompromise = 'update_mechanism_compromise';
    case SupplyChainAttack = 'supply_chain_attack';
    case InsufficientLogging = 'insufficient_logging';
    case DenialOfService = 'denial_of_service';
    case InsecureDefaults = 'insecure_defaults';
    case SecretsExposure = 'secrets_exposure';
    case CryptographicWeakness = 'cryptographic_weakness';
    case Tampering = 'tampering';
    case InsecureConfiguration = 'insecure_configuration';
    case UnsupportedComponent = 'unsupported_component';
}

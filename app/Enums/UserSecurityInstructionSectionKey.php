<?php

namespace App\Enums;

enum UserSecurityInstructionSectionKey: string
{
    case SecureInstallation = 'secure_installation';
    case MinimumEnvironment = 'minimum_environment';
    case RequiredPermissions = 'required_permissions';
    case SecureConfiguration = 'secure_configuration';
    case DefaultSettings = 'default_settings';
    case EncryptionRequirements = 'encryption_requirements';
    case Backup = 'backup';
    case Logging = 'logging';
    case UpdateProcedure = 'update_procedure';
    case SecurityContact = 'security_contact';
    case VulnerabilityReporting = 'vulnerability_reporting';
    case SupportPeriod = 'support_period';
    case EndOfSupportBehavior = 'end_of_support_behavior';
    case KnownLimitations = 'known_limitations';

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }

    public function defaultSortOrder(): int
    {
        $index = array_search($this, self::ordered(), true);

        return $index === false ? 0 : $index + 1;
    }
}

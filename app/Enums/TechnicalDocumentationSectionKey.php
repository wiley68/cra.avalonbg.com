<?php

namespace App\Enums;

enum TechnicalDocumentationSectionKey: string
{
    case ProductDescription = 'product_description';
    case IntendedPurpose = 'intended_purpose';
    case Architecture = 'architecture';
    case AttackSurface = 'attack_surface';
    case CybersecurityRiskAssessment = 'cybersecurity_risk_assessment';
    case EssentialRequirementsMatrix = 'essential_requirements_matrix';
    case DesignDevelopmentControls = 'design_development_controls';
    case ComponentInventory = 'component_inventory';
    case Sbom = 'sbom';
    case VulnerabilityHandlingProcess = 'vulnerability_handling_process';
    case UpdateMechanism = 'update_mechanism';
    case SecurityTests = 'security_tests';
    case SupportPeriod = 'support_period';
    case UserSecurityInstructions = 'user_security_instructions';
    case ConformityAssessmentPath = 'conformity_assessment_path';
    case DeclarationInformation = 'declaration_information';
    case ProductIdentification = 'product_identification';
    case ReleaseHistory = 'release_history';

    /**
     * Fixed §5.12 section order.
     *
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

    /**
     * Default content source for new packages (refresh/generate later).
     */
    public function defaultSource(): TechnicalDocumentationSectionSource
    {
        return match ($this) {
            self::CybersecurityRiskAssessment,
            self::EssentialRequirementsMatrix,
            self::DesignDevelopmentControls,
            self::ComponentInventory,
            self::Sbom,
            self::SupportPeriod,
            self::ProductIdentification,
            self::ReleaseHistory => TechnicalDocumentationSectionSource::Generated,

            self::UserSecurityInstructions => TechnicalDocumentationSectionSource::Linked,

            default => TechnicalDocumentationSectionSource::Authored,
        };
    }
}

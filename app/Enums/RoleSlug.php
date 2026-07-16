<?php

namespace App\Enums;

enum RoleSlug: string
{
    case Administrator = 'administrator';
    case OrganizationOwner = 'organization_owner';
    case ProductOwner = 'product_owner';
    case SecurityOwner = 'security_owner';
    case Developer = 'developer';
    case ComplianceReviewer = 'compliance_reviewer';
    case ReleaseApprover = 'release_approver';
    case Auditor = 'auditor';
    case ExternalConsultant = 'external_consultant';
    case ReadOnly = 'read_only';
}


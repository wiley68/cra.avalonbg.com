<?php

namespace App\Enums;

enum RequirementApplicabilityStatus: string
{
    case NotAssessed = 'not_assessed';
    case Applicable = 'applicable';
    case NotApplicable = 'not_applicable';
    case PartiallyImplemented = 'partially_implemented';
    case Implemented = 'implemented';
    case Verified = 'verified';
    case NonConformity = 'non_conformity';
    case ExceptionApproved = 'exception_approved';
}

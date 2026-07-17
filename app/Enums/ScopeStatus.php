<?php

namespace App\Enums;

enum ScopeStatus: string
{
    case LikelyInScope = 'likely_in_scope';
    case PotentiallyExcluded = 'potentially_excluded';
    case FurtherLegalReview = 'further_legal_review';
    case InsufficientInformation = 'insufficient_information';
    case OutOfScope = 'out_of_scope';
}

<?php

namespace App\Enums;

enum ProductVersionState: string
{
    case Draft = 'draft';
    case Development = 'development';
    case SecurityReview = 'security_review';
    case ReleaseCandidate = 'release_candidate';
    case Approved = 'approved';
    case Released = 'released';
    case Deprecated = 'deprecated';
    case EndOfSupport = 'end_of_support';
    case Withdrawn = 'withdrawn';
}

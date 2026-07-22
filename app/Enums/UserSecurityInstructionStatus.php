<?php

namespace App\Enums;

enum UserSecurityInstructionStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Published = 'published';
    case Retired = 'retired';
}

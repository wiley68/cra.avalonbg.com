<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Retired = 'retired';
}

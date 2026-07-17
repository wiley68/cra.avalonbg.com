<?php

namespace App\Enums;

enum ClassificationStatus: string
{
    case General = 'general';
    case ImportantClassI = 'important_class_i';
    case ImportantClassIi = 'important_class_ii';
    case Critical = 'critical';
    case Unclassified = 'unclassified';
    case UnderReview = 'under_review';
    case Excluded = 'excluded';
    case SectorSpecific = 'sector_specific';
}

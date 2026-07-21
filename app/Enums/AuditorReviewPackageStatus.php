<?php

namespace App\Enums;

enum AuditorReviewPackageStatus: string
{
    case Draft = 'draft';
    case Shared = 'shared';
    case Closed = 'closed';
}

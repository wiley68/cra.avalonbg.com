<?php

namespace App\Enums;

enum EvidenceFreshnessStatus: string
{
    case Current = 'current';
    case ReviewDue = 'review_due';
    case Expired = 'expired';
    case Superseded = 'superseded';
    case Invalid = 'invalid';
}

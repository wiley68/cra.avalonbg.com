<?php

namespace App\Enums;

enum AuditorFindingStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Remediated = 'remediated';
    case WontFix = 'wont_fix';
}

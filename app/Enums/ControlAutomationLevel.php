<?php

namespace App\Enums;

enum ControlAutomationLevel: string
{
    case Manual = 'manual';
    case SemiAutomated = 'semi_automated';
    case Automated = 'automated';
}

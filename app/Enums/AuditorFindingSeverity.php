<?php

namespace App\Enums;

enum AuditorFindingSeverity: string
{
    case Info = 'info';
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';
}

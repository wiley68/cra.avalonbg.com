<?php

namespace App\Enums;

enum SupportStatus: string
{
    case Supported = 'supported';
    case SecurityOnly = 'security_only';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';
}

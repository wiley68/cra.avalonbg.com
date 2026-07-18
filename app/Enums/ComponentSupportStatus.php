<?php

namespace App\Enums;

enum ComponentSupportStatus: string
{
    case Unknown = 'unknown';
    case Supported = 'supported';
    case Deprecated = 'deprecated';
    case EndOfLife = 'end_of_life';
}

<?php

namespace App\Enums;

enum VcsConnectionStatus: string
{
    case Active = 'active';
    case Invalid = 'invalid';
    case Revoked = 'revoked';
}

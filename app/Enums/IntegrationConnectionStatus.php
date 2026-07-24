<?php

namespace App\Enums;

enum IntegrationConnectionStatus: string
{
    case Active = 'active';
    case Invalid = 'invalid';
    case Revoked = 'revoked';
}

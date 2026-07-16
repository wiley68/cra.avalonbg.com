<?php

namespace App\Enums;

enum RoleScope: string
{
    case System = 'system';
    case Organization = 'organization';
}


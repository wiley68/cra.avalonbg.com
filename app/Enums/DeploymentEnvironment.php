<?php

namespace App\Enums;

enum DeploymentEnvironment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Other = 'other';
}

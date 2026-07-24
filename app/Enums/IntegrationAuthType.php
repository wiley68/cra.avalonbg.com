<?php

namespace App\Enums;

enum IntegrationAuthType: string
{
    case ApiToken = 'api_token';
    case None = 'none';
}

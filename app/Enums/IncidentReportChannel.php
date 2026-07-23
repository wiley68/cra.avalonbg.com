<?php

namespace App\Enums;

enum IncidentReportChannel: string
{
    case Email = 'email';
    case Portal = 'portal';
    case Other = 'other';
}

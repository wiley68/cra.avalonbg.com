<?php

namespace App\Enums;

enum IncidentCommunicationChannel: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Portal = 'portal';
    case Meeting = 'meeting';
    case Other = 'other';
}

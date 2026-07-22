<?php

namespace App\Enums;

enum AiDraftType: string
{
    case SecurityAdvisory = 'security_advisory';
    case CustomerNotification = 'customer_notification';
}

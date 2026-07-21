<?php

namespace App\Enums;

enum PatchCampaignTargetNotificationChannel: string
{
    case Manual = 'manual';
    case Email = 'email';
}

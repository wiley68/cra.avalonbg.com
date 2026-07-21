<?php

namespace App\Enums;

enum PatchCampaignTargetNotificationEventType: string
{
    case StatusChanged = 'status_changed';
    case EmailQueued = 'email_queued';
    case EmailSent = 'email_sent';
}

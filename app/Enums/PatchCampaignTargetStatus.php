<?php

namespace App\Enums;

enum PatchCampaignTargetStatus: string
{
    case Pending = 'pending';
    case Notified = 'notified';
    case Acknowledged = 'acknowledged';
    case Updated = 'updated';
    case Excepted = 'excepted';
}

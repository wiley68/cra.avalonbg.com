<?php

namespace App\Enums;

enum IntegrationSyncRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}

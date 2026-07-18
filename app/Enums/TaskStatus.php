<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case PendingApproval = 'pending_approval';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

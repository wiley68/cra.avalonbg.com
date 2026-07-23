<?php

namespace App\Enums;

enum SdlStageStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Na = 'na';
    case Exception = 'exception';

    public function isComplete(): bool
    {
        return in_array($this, [self::Done, self::Na, self::Exception], true);
    }

    public function requiresFollowUp(): bool
    {
        return $this === self::Exception;
    }
}

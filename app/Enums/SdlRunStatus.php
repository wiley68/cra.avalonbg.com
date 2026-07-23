<?php

namespace App\Enums;

enum SdlRunStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::Draft,
            self::InProgress,
            self::Blocked,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Cancelled], true);
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}

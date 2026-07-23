<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Contained = 'contained';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::Open,
            self::Investigating,
            self::Contained,
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }
}

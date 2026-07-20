<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum VcsSyncSchedule: string
{
    case Off = 'off';
    case Hourly = 'hourly';
    case Daily = 'daily';

    public function isDue(?CarbonInterface $lastSyncedAt, ?CarbonInterface $now = null): bool
    {
        if ($this === self::Off) {
            return false;
        }

        $now ??= now();

        if ($lastSyncedAt === null) {
            return true;
        }

        return match ($this) {
            self::Hourly => $lastSyncedAt->lte($now->copy()->subHour()),
            self::Daily => $lastSyncedAt->lte($now->copy()->subDay()),
            self::Off => false,
        };
    }
}

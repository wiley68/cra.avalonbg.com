<?php

namespace App\Enums;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

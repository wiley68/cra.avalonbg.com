<?php

namespace App\Enums;

enum SsoProvider: string
{
    case Generic = 'generic';
    case Entra = 'entra';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum BillingDocumentType: string
{
    case Invoice = 'invoice';
    case License = 'license';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

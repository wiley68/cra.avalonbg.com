<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Bank = 'bank';
    case Stripe = 'stripe';
    case AdminComp = 'admin_comp';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

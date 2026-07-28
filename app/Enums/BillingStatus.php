<?php

namespace App\Enums;

enum BillingStatus: string
{
    case Active = 'active';
    case PendingPayment = 'pending_payment';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

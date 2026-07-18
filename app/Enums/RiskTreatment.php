<?php

namespace App\Enums;

enum RiskTreatment: string
{
    case Mitigate = 'mitigate';
    case Accept = 'accept';
    case Transfer = 'transfer';
    case Avoid = 'avoid';
}

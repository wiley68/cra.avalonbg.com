<?php

namespace App\Enums;

enum LicensingModel: string
{
    case Paid = 'paid';
    case Free = 'free';
    case MonetisedIndirectly = 'monetised_indirectly';
    case Unknown = 'unknown';
}

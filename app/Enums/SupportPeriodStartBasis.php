<?php

namespace App\Enums;

enum SupportPeriodStartBasis: string
{
    case ReleaseDate = 'release_date';
    case PurchaseDate = 'purchase_date';
    case ContractStartDate = 'contract_start_date';
    case ActivationDate = 'activation_date';
    case Custom = 'custom';
}

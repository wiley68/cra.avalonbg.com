<?php

namespace App\Enums;

enum ScopeMarketRole: string
{
    case Manufacturer = 'manufacturer';
    case Importer = 'importer';
    case Distributor = 'distributor';
    case Unsure = 'unsure';
}

<?php

namespace App\Enums;

enum ProductControlStatus: string
{
    case Planned = 'planned';
    case InPlace = 'in_place';
    case Partial = 'partial';
    case NotApplicable = 'not_applicable';
}

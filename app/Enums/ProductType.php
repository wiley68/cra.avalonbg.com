<?php

namespace App\Enums;

enum ProductType: string
{
    case Software = 'software';
    case Hardware = 'hardware';
    case Other = 'other';
}

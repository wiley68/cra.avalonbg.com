<?php

namespace App\Enums;

enum CustomerCriticality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

<?php

namespace App\Enums;

enum RiskImpact: int
{
    case VeryLow = 1;
    case Low = 2;
    case Medium = 3;
    case High = 4;
    case VeryHigh = 5;
}

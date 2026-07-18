<?php

namespace App\Enums;

enum ProductRiskStatus: string
{
    case Open = 'open';
    case InTreatment = 'in_treatment';
    case Accepted = 'accepted';
    case Closed = 'closed';
}

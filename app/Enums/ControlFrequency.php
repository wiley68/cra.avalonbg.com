<?php

namespace App\Enums;

enum ControlFrequency: string
{
    case Continuous = 'continuous';
    case PerRelease = 'per_release';
    case Periodic = 'periodic';
    case OnDemand = 'on_demand';
    case AdHoc = 'ad_hoc';
}

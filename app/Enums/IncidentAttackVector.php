<?php

namespace App\Enums;

enum IncidentAttackVector: string
{
    case Network = 'network';
    case Adjacent = 'adjacent';
    case Local = 'local';
    case Physical = 'physical';
}

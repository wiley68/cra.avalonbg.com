<?php

namespace App\Enums;

enum EvidenceConfidentiality: string
{
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
}

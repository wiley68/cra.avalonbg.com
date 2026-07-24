<?php

namespace App\Enums;

enum ImportSuggestionKind: string
{
    case Task = 'task';
    case Vulnerability = 'vulnerability';
    case EvidenceRef = 'evidence_ref';
}

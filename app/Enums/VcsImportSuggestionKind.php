<?php

namespace App\Enums;

enum VcsImportSuggestionKind: string
{
    case Version = 'version';
    case Vulnerability = 'vulnerability';
}

<?php

namespace App\Enums;

enum VcsImportSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';
}

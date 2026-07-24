<?php

namespace App\Enums;

enum ImportSuggestionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';
}

<?php

namespace App\Enums;

enum TechnicalDocumentationSectionSource: string
{
    case Generated = 'generated';
    case Authored = 'authored';
    case Linked = 'linked';
}

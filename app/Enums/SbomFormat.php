<?php

namespace App\Enums;

enum SbomFormat: string
{
    case CycloneDxJson = 'cyclonedx_json';
    case ComposerLock = 'composer_lock';
    case Manual = 'manual';
}

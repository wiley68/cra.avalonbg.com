<?php

namespace App\Enums;

enum VcsProvider: string
{
    case Github = 'github';
    case Gitlab = 'gitlab';
}

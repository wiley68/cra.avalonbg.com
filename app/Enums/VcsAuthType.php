<?php

namespace App\Enums;

enum VcsAuthType: string
{
    case Pat = 'pat';
    case GithubApp = 'github_app';
}

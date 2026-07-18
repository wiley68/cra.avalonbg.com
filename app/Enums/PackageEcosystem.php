<?php

namespace App\Enums;

enum PackageEcosystem: string
{
    case Composer = 'composer';
    case Npm = 'npm';
    case Nuget = 'nuget';
    case Maven = 'maven';
    case Pypi = 'pypi';
    case FirstParty = 'first_party';
    case Other = 'other';
}

<?php

namespace App\Enums;

enum AiEmbeddingSourceType: string
{
    case RequirementVersion = 'requirement_version';
    case OrgPolicy = 'org_policy';
    case Evidence = 'evidence';
    case Control = 'control';
}

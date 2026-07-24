<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Jira = 'jira';
    case Snyk = 'snyk';
    case AzureDevops = 'azure_devops';
    case Sarif = 'sarif';

    public function category(): IntegrationCategory
    {
        return match ($this) {
            self::Jira, self::AzureDevops => IntegrationCategory::Alm,
            self::Snyk, self::Sarif => IntegrationCategory::Scanner,
        };
    }
}

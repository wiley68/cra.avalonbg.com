<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Jira = 'jira';
    case Snyk = 'snyk';
    case AzureDevops = 'azure_devops';

    public function category(): IntegrationCategory
    {
        return match ($this) {
            self::Jira, self::AzureDevops => IntegrationCategory::Alm,
            self::Snyk => IntegrationCategory::Scanner,
        };
    }
}

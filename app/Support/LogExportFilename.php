<?php

namespace App\Support;

class LogExportFilename
{
    public static function users(string $organizationSlug): string
    {
        $safeSlug = preg_replace('/[^a-z0-9\-_]+/i', '-', $organizationSlug) ?: 'organization';

        return 'users_'.$safeSlug.'_'.now()->format('Y-m-d_H-i-s').'.xlsx';
    }

    public static function archiveFromXlsx(string $xlsxFilename): string
    {
        return pathinfo(basename($xlsxFilename), PATHINFO_FILENAME).'.7z';
    }
}

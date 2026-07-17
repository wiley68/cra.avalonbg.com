<?php

namespace App\Enums;

use App\Support\Translations;

enum AuditEventSource: string
{
    case Workspace = 'workspace';
    case Api = 'api';

    public function label(): string
    {
        return Translations::get('audit_logs.event_sources.' . $this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn(self $case) => $case->value, self::cases()),
            array_map(fn(self $case) => $case->label(), self::cases()),
        );
    }
}

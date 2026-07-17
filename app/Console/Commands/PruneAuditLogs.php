<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit-logs:prune';

    protected $description = 'Delete audit log records older than the configured retention period';

    public function handle(): int
    {
        $years = (int) config('retention.audit_logs_years', 1);
        $threshold = now()->subYears($years);

        $deleted = AuditLog::query()
            ->where('occurred_at', '<', $threshold)
            ->delete();

        $this->info("Deleted {$deleted} audit log(s) older than {$years} year(s).");

        return self::SUCCESS;
    }
}

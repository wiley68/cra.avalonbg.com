<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

class OpsBaselineCheck extends Command
{
    protected $signature = 'ops:baseline-check';

    protected $description = 'Verify scheduler + queue baseline for scheduled VCS/integration sync (Phase 2_E Must 1–2)';

    public function handle(): int
    {
        $ok = true;

        $queue = (string) config('queue.default');
        if ($queue === 'sync') {
            $this->error('QUEUE_CONNECTION is "sync" — scheduled dispatch() will run inline and block schedule:run. Use database or redis.');
            $ok = false;
        } else {
            $this->info("Queue connection: {$queue}");
        }

        if ($queue === 'database') {
            foreach (['jobs', 'failed_jobs'] as $table) {
                try {
                    $exists = Schema::hasTable($table);
                } catch (\Throwable $e) {
                    $this->error("Cannot check table [{$table}]: " . $e->getMessage());
                    $ok = false;

                    continue;
                }

                if (!$exists) {
                    $this->error("Missing table [{$table}]. Run php artisan migrate.");
                    $ok = false;
                } else {
                    $this->line("Table [{$table}]: ok");
                }
            }

            if (Schema::hasTable('failed_jobs')) {
                try {
                    $failedCount = (int) DB::table('failed_jobs')->count();
                    if ($failedCount > 0) {
                        $this->warn("failed_jobs count: {$failedCount} — inspect with `php artisan queue:failed`");
                    } else {
                        $this->line('failed_jobs count: 0');
                    }
                } catch (\Throwable $e) {
                    $this->warn('Could not count failed_jobs: ' . $e->getMessage());
                }
            }

            $retryAfter = (int) config('queue.connections.database.retry_after', 90);
            $jobTimeout = 90;
            if ($retryAfter <= $jobTimeout) {
                $this->error("database retry_after ({$retryAfter}) must be greater than sync job timeout ({$jobTimeout}). Set DB_QUEUE_RETRY_AFTER>=150.");
                $ok = false;
            } else {
                $this->info("database retry_after: {$retryAfter} (> job timeout {$jobTimeout})");
            }
        }

        $required = [
            'vcs:sync-scheduled' => '0 * * * *',
            'integrations:sync-scheduled' => '0 * * * *',
        ];

        $events = collect(Schedule::events());

        foreach ($required as $command => $expression) {
            $match = $events->first(
                fn($event) => str_contains((string) ($event->command ?? ''), $command),
            );

            if ($match === null) {
                $this->error("Schedule missing: {$command}");
                $ok = false;

                continue;
            }

            if ($match->expression !== $expression) {
                $this->error("Schedule [{$command}] expected [{$expression}], got [{$match->expression}]");
                $ok = false;

                continue;
            }

            $this->info("Schedule [{$command}]: {$match->expression}");
        }

        $this->newLine();
        $this->line('Reminder: cron must call `php artisan schedule:run` every minute (or use ops/samples/systemd timer).');
        $this->line('Reminder: `php artisan queue:work --tries=3 --timeout=90` must consume dispatched jobs (see ops/samples/).');
        $this->line('Reminder: daily `ai:index-embeddings` (RAG) also needs schedule + worker unless CRA_AI_RAG_REINDEX_SCHEDULE=off.');
        $this->line('Manual Sync now uses dispatchSync and does not require the worker.');
        $this->line('Hard sync failures: 3 tries with backoff 15/60/120s; then failed_jobs + last_sync_summary.queue_failed.');

        if ($ok) {
            $this->newLine();
            $this->info('Ops baseline check passed.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('Ops baseline check failed.');

        return self::FAILURE;
    }
}

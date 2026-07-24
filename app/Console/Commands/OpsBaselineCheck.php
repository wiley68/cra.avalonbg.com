<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

class OpsBaselineCheck extends Command
{
    protected $signature = 'ops:baseline-check';

    protected $description = 'Verify scheduler + queue baseline for scheduled VCS/integration sync (Phase 2_E Must 1)';

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
        $this->line('Reminder: cron must call `php artisan schedule:run` every minute.');
        $this->line('Reminder: `php artisan queue:work` must consume dispatched jobs.');
        $this->line('Manual Sync now uses dispatchSync and does not require the worker.');

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

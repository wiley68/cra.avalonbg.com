<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit-logs:prune')->daily();
Schedule::command('evidence:refresh-freshness')->daily();
Schedule::command('vcs:sync-scheduled')->hourly();
Schedule::command('integrations:sync-scheduled')->hourly();

$ragReindexSchedule = strtolower((string) config('ai.rag.reindex_schedule', 'daily'));
$ragReindexEnabled = fn(): bool => (bool) config('ai.rag.enabled', true);

if ($ragReindexSchedule === 'hourly') {
    Schedule::command('ai:index-embeddings')
        ->hourly()
        ->withoutOverlapping(120)
        ->when($ragReindexEnabled);
} elseif ($ragReindexSchedule !== 'off') {
    $reindexAt = (string) config('ai.rag.reindex_at', '02:30');
    if (!preg_match('/^\d{1,2}:\d{2}$/', $reindexAt)) {
        $reindexAt = '02:30';
    }

    Schedule::command('ai:index-embeddings')
        ->dailyAt($reindexAt)
        ->withoutOverlapping(120)
        ->when($ragReindexEnabled);
}

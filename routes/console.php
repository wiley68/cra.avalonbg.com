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

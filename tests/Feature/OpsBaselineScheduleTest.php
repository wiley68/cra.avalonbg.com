<?php

use App\Jobs\SyncProductIntegrationJob;
use App\Jobs\SyncProductRepositoryJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

test('ops baseline check passes with database queue and hourly sync schedules', function () {
    config(['queue.default' => 'database']);

    $exit = Artisan::call('ops:baseline-check');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Ops baseline check passed')
        ->and($output)->toContain('vcs:sync-scheduled')
        ->and($output)->toContain('integrations:sync-scheduled')
        ->and($output)->toContain('Table [jobs]: ok');
});

test('ops baseline check fails when queue connection is sync', function () {
    config(['queue.default' => 'sync']);

    $exit = Artisan::call('ops:baseline-check');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('QUEUE_CONNECTION is "sync"');
});

test('scheduled sync jobs implement ShouldQueue for worker consumption', function () {
    expect(is_subclass_of(SyncProductRepositoryJob::class, ShouldQueue::class))->toBeTrue()
        ->and(is_subclass_of(SyncProductIntegrationJob::class, ShouldQueue::class))->toBeTrue();
});

test('both sync-scheduled commands are registered hourly on the scheduler', function () {
    $events = collect(Schedule::events());

    foreach (['vcs:sync-scheduled', 'integrations:sync-scheduled'] as $command) {
        $match = $events->first(
            fn($event) => str_contains((string) ($event->command ?? ''), $command),
        );

        expect($match)->not->toBeNull()
            ->and($match->expression)->toBe('0 * * * *');
    }
});

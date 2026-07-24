<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

test('ai:index-embeddings is scheduled daily by default for RAG reindex', function () {
    config([
        'ai.rag.enabled' => true,
        'ai.rag.reindex_schedule' => 'daily',
        'ai.rag.reindex_at' => '02:30',
    ]);

    $match = collect(Schedule::events())->first(
        fn($event) => str_contains((string) ($event->command ?? ''), 'ai:index-embeddings'),
    );

    expect($match)->not->toBeNull()
        ->and($match->expression)->toBe('30 2 * * *');
});

test('scheduled RAG reindex when() respects CRA_AI_RAG_ENABLED', function () {
    $match = collect(Schedule::events())->first(
        fn($event) => str_contains((string) ($event->command ?? ''), 'ai:index-embeddings'),
    );

    expect($match)->not->toBeNull();

    config(['ai.rag.enabled' => false]);
    expect($match->filtersPass(app()))->toBeFalse();

    config(['ai.rag.enabled' => true]);
    expect($match->filtersPass(app()))->toBeTrue();
});

test('ops ai-check reports RAG reindex schedule', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.rag.enabled' => true,
        'ai.rag.reindex_schedule' => 'daily',
        'ai.rag.reindex_at' => '02:30',
    ]);

    $exit = Artisan::call('ops:ai-check');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('CRA_AI_RAG_ENABLED: true')
        ->and($output)->toContain('CRA_AI_RAG_REINDEX_SCHEDULE: daily @ 02:30')
        ->and($output)->toContain('ai:index-embeddings');
});

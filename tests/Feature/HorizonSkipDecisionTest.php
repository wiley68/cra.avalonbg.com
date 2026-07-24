<?php

test('horizon package is not part of the stack (Could 14 skip)', function () {
    expect(file_exists(base_path('vendor/laravel/horizon')))->toBeFalse()
        ->and(file_exists(base_path('config/horizon.php')))->toBeFalse()
        ->and(file_get_contents(base_path('composer.json')))->not->toContain('laravel/horizon');

    // Failed-job storage stays on the database driver path (Must 2).
    expect(config('queue.failed.driver'))->toBe('database-uuids')
        ->and(config('queue.failed.table'))->toBe('failed_jobs');
});

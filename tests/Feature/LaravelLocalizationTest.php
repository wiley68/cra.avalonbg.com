<?php

test('laravel validation and pagination messages resolve for bg and en', function () {
    app()->setLocale('bg');

    expect(__('validation.required'))->toBe('Полето :attribute е задължително.')
        ->and(__('pagination.next'))->toBe('Напред &raquo;')
        ->and(__('auth.failed'))->not->toBe('auth.failed')
        ->and(__('passwords.reset'))->not->toBe('passwords.reset')
        ->and(__('http-statuses.404'))->toBe('Не е намерено');

    app()->setLocale('en');

    expect(__('validation.required'))->toBe('The :attribute field is required.')
        ->and(__('pagination.next'))->toBe('Next &raquo;')
        ->and(__('http-statuses.404'))->toBe('Not Found');
});

test('nested app json translations remain intact after lang sync', function () {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);
    $bg = json_decode((string) file_get_contents(lang_path('bg.json')), true);

    expect($en)->toBeArray()
        ->and($bg)->toBeArray()
        ->and($en['settings']['password']['heading'] ?? null)->toBe('Update password')
        ->and($bg['settings']['password']['heading'] ?? null)->toBe('Обновяване на паролата')
        ->and($en['common'] ?? null)->toBeArray()
        ->and($bg['common'] ?? null)->toBeArray();
});

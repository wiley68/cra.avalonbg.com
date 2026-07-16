<?php

use Laravel\Fortify\Features;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('registration feature is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});


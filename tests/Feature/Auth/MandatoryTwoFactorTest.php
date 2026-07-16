<?php

use App\Models\User;
use Laravel\Fortify\Features;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('verified user without 2fa is redirected to setup page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('auth.two-factor.setup'));
});


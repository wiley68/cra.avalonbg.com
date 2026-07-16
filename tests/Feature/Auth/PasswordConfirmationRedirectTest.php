<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('password confirmation redirects back to the intended security settings page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));

    expect(session('url.intended'))->toEndWith('/settings/security');

    $this->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ])
        ->assertRedirect('/settings/security');
});

test('password confirmation uses redirect form field when session intended is missing', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
            'redirect' => '/settings/security',
        ])
        ->assertRedirect('/settings/security');
});

test('password confirmation rejects external redirect targets', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'password',
            'redirect' => 'https://evil.example/phish',
        ])
        ->assertRedirect('/dashboard');
});

test('confirm password page includes the intended redirect prop', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['url.intended' => url('/settings/security')])
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('auth/ConfirmPassword')
            ->where('redirect', url('/settings/security')));
});

test('security settings is available after password confirmation', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->withSession(['url.intended' => route('security.edit')])
        ->post(route('password.confirm.store'), [
            'password' => 'password',
        ])
        ->assertRedirect('/settings/security');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('settings/Security'));
});

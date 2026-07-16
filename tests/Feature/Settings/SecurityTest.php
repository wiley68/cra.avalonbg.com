<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('settings/Security')
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', true)
                ->missing('canManagePasskeys')
                ->missing('passkeys'),
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page) => $page
                ->component('settings/Security')
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation')
                ->missing('canManagePasskeys')
                ->missing('passkeys'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('NewPassword1!', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

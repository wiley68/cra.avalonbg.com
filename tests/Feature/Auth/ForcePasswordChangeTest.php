<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user with must change password is redirected to force password page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('auth.force-password.edit'));
});

test('user can change forced password and continue', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => true,
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->put(route('auth.force-password.update'), [
        'password' => 'NewPassword!1234',
        'password_confirmation' => 'NewPassword!1234',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('NewPassword!1234', $user->fresh()->password))->toBeTrue();
});


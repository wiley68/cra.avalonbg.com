<?php

use App\Enums\Appearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

test('authenticated user appearance is loaded from profile', function () {
    $user = User::factory()->create([
        'appearance' => Appearance::Dark->value,
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('appearance', Appearance::Dark->value));
});

test('guest appearance falls back to cookie', function () {
    $appearance = Appearance::Light->value;

    /** @var TestCase $this */
    $this->withUnencryptedCookie('appearance', $appearance)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('appearance', $appearance));
});

test('user can update appearance in profile settings', function () {
    $user = User::factory()->create([
        'appearance' => Appearance::System->value,
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    actingAs($user);

    patch(route('appearance.update'), [
        'appearance' => Appearance::Dark->value,
    ])
        ->assertRedirect(route('appearance.edit'));

    expect($user->refresh()->appearance)->toBe(Appearance::Dark->value);
});

test('invalid appearance value is rejected', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    actingAs($user);

    patch(route('appearance.update'), [
        'appearance' => 'neon',
    ])->assertSessionHasErrors('appearance');
});

test('login restores appearance from user profile on first page load', function () {
    $user = User::factory()->create([
        'appearance' => Appearance::Dark->value,
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => null,
        'password' => 'password',
    ]);

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('auth.two-factor.setup'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('appearance', Appearance::Dark->value));
});

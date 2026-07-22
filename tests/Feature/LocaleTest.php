<?php

use App\Support\Translations;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('home page defaults to english locale', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'en')
            ->where('translations_version', Translations::version('en'))
            ->missing('translations'));

    $this->get(route('translations.show', ['locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('welcome.sign_in', 'Sign in');
});

test('locale can be switched and stored in session', function () {
    $this->from(route('home'))
        ->get(route('locale.update', ['locale' => 'bg']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'bg')
            ->where('translations_version', Translations::version('bg'))
            ->missing('translations'));

    $this->get(route('translations.show', ['locale' => 'bg']))
        ->assertOk()
        ->assertJsonPath('welcome.sign_in', 'Вход');
});

test('invalid locale returns not found', function () {
    $this->get('/locale/fr')->assertNotFound();
});

test('translations helper resolves nested english and bulgarian keys', function () {
    expect(Translations::get('welcome.sign_in'))->toBe('Sign in')
        ->and(Translations::get('admin.users.title'))->toBe('Users')
        ->and(Translations::get('auth.forgot_password.title'))->toBe('Forgot password')
        ->and(Translations::get('welcome.sign_in', locale: 'bg'))->toBe('Вход')
        ->and(Translations::get('admin.users.title', locale: 'bg'))->toBe('Потребители')
        ->and(Translations::get('auth.forgot_password.title', locale: 'bg'))->toBe('Забравена парола');
});

test('password reset status messages are translated for bulgarian locale', function () {
    app()->setLocale('bg');

    expect(__('passwords.sent'))->toBe('Изпратихме ви връзка за нулиране на паролата по имейл.')
        ->and(__('auth.failed'))->toBe('Тези данни за вход не съвпадат с нашите записи.');
});

test('organization member session locale follows organization locale', function () {
    test()->seed([\Database\Seeders\RolePermissionSeeder::class]);

    $organization = \App\Models\Organization::query()->create([
        'name' => 'Locale Org',
        'slug' => 'locale-org',
        'is_active' => true,
        'locale' => 'bg',
    ]);

    $owner = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = \App\Models\Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($owner)
        ->withSession(['locale' => 'en'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('locale', 'bg'));

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->get(route('locale.update', ['locale' => 'en']))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('locale', 'bg'));
});

test('platform admin without organization can still switch personal locale', function () {
    test()->seed([\Database\Seeders\RolePermissionSeeder::class]);

    $admin = \App\Models\User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('dashboard'))
        ->get(route('locale.update', ['locale' => 'bg']))
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('locale', 'bg'));
});

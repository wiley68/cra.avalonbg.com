<?php

use App\Support\Translations;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('home page defaults to english locale', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'en')
            ->where('translations.welcome.sign_in', 'Sign in'));
});

test('locale can be switched and stored in session', function () {
    $this->from(route('home'))
        ->get(route('locale.update', ['locale' => 'bg']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('locale', 'bg')
            ->where('translations.welcome.sign_in', 'Вход'));
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

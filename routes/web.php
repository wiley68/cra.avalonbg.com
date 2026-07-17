<?php

use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\OrganizationUserController as AdminOrganizationUserController;
use App\Http\Controllers\Api\Admin\OrganizationApiController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('locale/{locale}', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('auth/force-password-change', [ForcePasswordChangeController::class, 'edit'])->name('auth.force-password.edit');
    Route::put('auth/force-password-change', [ForcePasswordChangeController::class, 'update'])->name('auth.force-password.update');
    Route::get('auth/two-factor-setup', TwoFactorSetupController::class)
        ->middleware('password.confirm')
        ->name('auth.two-factor.setup');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware(['password.changed', 'two-factor.enabled'])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::resource('users', UserController::class)->except(['show']);

        Route::prefix('admin')->name('admin.')->middleware('can:platform.admin')->group(function () {
            Route::resource('organizations', AdminOrganizationController::class)
                ->except(['show', 'destroy']);

            Route::resource('organizations.users', AdminOrganizationUserController::class)
                ->except(['show'])
                ->scoped();

            Route::prefix('internal-api')->name('internal.')->group(function () {
                Route::get('organizations', [OrganizationApiController::class, 'index'])
                    ->name('organizations.index');
            });
        });
    });
});

require __DIR__ . '/settings.php';

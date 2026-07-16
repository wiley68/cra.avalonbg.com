<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('auth/force-password-change', [ForcePasswordChangeController::class, 'edit'])->name('auth.force-password.edit');
    Route::put('auth/force-password-change', [ForcePasswordChangeController::class, 'update'])->name('auth.force-password.update');
    Route::get('auth/two-factor-setup', TwoFactorSetupController::class)->name('auth.two-factor.setup');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware(['password.changed', 'two-factor.enabled'])->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');

        Route::prefix('admin')->name('admin.')->middleware('can:platform.admin')->group(function () {
            Route::resource('users', AdminUserController::class)->except(['show', 'destroy']);
        });
    });
});

require __DIR__.'/settings.php';

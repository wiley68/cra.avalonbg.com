<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\OrganizationUserController as AdminOrganizationUserController;
use App\Http\Controllers\Api\Admin\AuditLogApiController;
use App\Http\Controllers\Api\Admin\OrganizationApiController;
use App\Http\Controllers\Api\Admin\OrganizationUserApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductVersionApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductScopeAssessmentController;
use App\Http\Controllers\ProductVersionController;
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

        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('products/scope-assessment/preview', [ProductScopeAssessmentController::class, 'preview'])
            ->name('products.scope-assessment.preview');
        Route::get('products/{product}/scope-assessments/latest', [ProductScopeAssessmentController::class, 'show'])
            ->name('products.scope-assessments.latest');
        Route::post('products/{product}/scope-assessments', [ProductScopeAssessmentController::class, 'store'])
            ->name('products.scope-assessments.store');
        Route::resource('products.versions', ProductVersionController::class)
            ->except(['show'])
            ->parameters(['versions' => 'version'])
            ->scoped();

        Route::prefix('internal-api')->name('internal.')->group(function () {
            Route::get('users', [UserApiController::class, 'index'])
                ->name('users.index');
            Route::get('products', [ProductApiController::class, 'index'])
                ->name('products.index');
            Route::get('products/{product}/versions', [ProductVersionApiController::class, 'index'])
                ->name('products.versions.index');
        });

        Route::prefix('admin')->name('admin.')->middleware('can:platform.admin')->group(function () {
            Route::resource('organizations', AdminOrganizationController::class)
                ->except(['show', 'destroy']);

            Route::resource('organizations.users', AdminOrganizationUserController::class)
                ->except(['show'])
                ->scoped();

            Route::get('audit-logs', [AdminAuditLogController::class, 'index'])
                ->name('audit-logs.index');

            Route::prefix('internal-api')->name('internal.')->group(function () {
                Route::get('organizations', [OrganizationApiController::class, 'index'])
                    ->name('organizations.index');

                Route::get('organizations/{organization}/users', [OrganizationUserApiController::class, 'index'])
                    ->name('organizations.users.index');

                Route::get('audit-logs', [AuditLogApiController::class, 'index'])
                    ->name('audit-logs.index');
            });
        });
    });
});

require __DIR__ . '/settings.php';

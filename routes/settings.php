<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\OrganizationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', 'password.changed'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('settings/organization', [OrganizationController::class, 'update'])
        ->name('settings.organization.update');
    Route::delete('settings/organization', [OrganizationController::class, 'destroy'])
        ->name('settings.organization.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::get('settings/billing', [BillingController::class, 'edit'])
        ->name('settings.billing.edit');
    Route::post('settings/billing/bank-payment', [BillingController::class, 'requestBankPayment'])
        ->name('settings.billing.bank-payment.store');

    Route::get('settings/integrations', [IntegrationController::class, 'edit'])
        ->name('settings.integrations.edit');
    Route::post('settings/integrations/github', [IntegrationController::class, 'storeGithub'])
        ->name('settings.integrations.github.store');
    Route::post('settings/integrations/github/app', [IntegrationController::class, 'storeGithubApp'])
        ->name('settings.integrations.github.app.store');
    Route::post('settings/integrations/gitlab', [IntegrationController::class, 'storeGitlab'])
        ->name('settings.integrations.gitlab.store');
    Route::post('settings/integrations/jira', [IntegrationController::class, 'storeJira'])
        ->name('settings.integrations.jira.store');
    Route::post('settings/integrations/snyk', [IntegrationController::class, 'storeSnyk'])
        ->name('settings.integrations.snyk.store');
    Route::post('settings/integrations/azure-devops', [IntegrationController::class, 'storeAzureDevOps'])
        ->name('settings.integrations.azure-devops.store');
    Route::post('settings/integrations/sarif', [IntegrationController::class, 'storeSarif'])
        ->name('settings.integrations.sarif.store');
    Route::put('settings/integrations/{connection}/sync-schedule', [IntegrationController::class, 'updateSyncSchedule'])
        ->name('settings.integrations.sync-schedule.update');
    Route::put(
        'settings/integrations/providers/{integration}/sync-schedule',
        [IntegrationController::class, 'updateIntegrationSyncSchedule'],
    )->name('settings.integrations.providers.sync-schedule.update');
    Route::post('settings/integrations/{connection}/webhook-secret', [IntegrationController::class, 'rotateWebhookSecret'])
        ->name('settings.integrations.webhook-secret.rotate');
    Route::delete('settings/integrations/{connection}', [IntegrationController::class, 'destroy'])
        ->name('settings.integrations.destroy');
    Route::delete('settings/integrations/providers/{integration}', [IntegrationController::class, 'destroyIntegration'])
        ->name('settings.integrations.providers.destroy');
});

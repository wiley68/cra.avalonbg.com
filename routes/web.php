<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\OrganizationController as AdminOrganizationController;
use App\Http\Controllers\Admin\OrganizationUserController as AdminOrganizationUserController;
use App\Http\Controllers\Admin\RequirementController as AdminRequirementController;
use App\Http\Controllers\Api\Admin\AuditLogApiController as AdminAuditLogApiController;
use App\Http\Controllers\Api\Admin\OrganizationApiController;
use App\Http\Controllers\Api\Admin\OrganizationUserApiController;
use App\Http\Controllers\Api\Admin\RequirementApiController;
use App\Http\Controllers\Api\AuditLogApiController;
use App\Http\Controllers\Api\ControlApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\EvidenceApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ProductComponentApiController;
use App\Http\Controllers\Api\ProductControlApiController;
use App\Http\Controllers\Api\ProductDeploymentApiController;
use App\Http\Controllers\Api\PatchCampaignApiController;
use App\Http\Controllers\Api\ProductRequirementApiController;
use App\Http\Controllers\Api\ProductRiskApiController;
use App\Http\Controllers\Api\ProductSupportPeriodApiController;
use App\Http\Controllers\Api\ProductVersionApiController;
use App\Http\Controllers\Api\ProductVulnerabilityApiController;
use App\Http\Controllers\Api\TaskApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductClassificationController;
use App\Http\Controllers\ProductCompliancePassportController;
use App\Http\Controllers\ProductComponentController;
use App\Http\Controllers\ProductControlController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDeploymentController;
use App\Http\Controllers\PatchCampaignController;
use App\Http\Controllers\ProductReadinessController;
use App\Http\Controllers\ProductRepositoryController;
use App\Http\Controllers\ProductRequirementController;
use App\Http\Controllers\ProductRiskController;
use App\Http\Controllers\ProductScopeAssessmentController;
use App\Http\Controllers\ProductSupportPeriodController;
use App\Http\Controllers\ProductVersionController;
use App\Http\Controllers\ProductVcsImportSuggestionController;
use App\Http\Controllers\ProductVulnerabilityController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VulnerabilityReportingController;
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
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor'])
            ->name('users.reset-two-factor');
        Route::post('users/export', [UserController::class, 'export'])
            ->name('users.export');

        Route::post('controls/refresh-starter', [ControlController::class, 'refreshStarter'])
            ->name('controls.refresh-starter');
        Route::resource('controls', ControlController::class)->except(['show']);
        Route::get('customers/import/template', [CustomerController::class, 'importTemplate'])
            ->name('customers.import.template');
        Route::get('customers/import', [CustomerController::class, 'importForm'])
            ->name('customers.import');
        Route::post('customers/import', [CustomerController::class, 'import'])
            ->name('customers.import.store');
        Route::resource('customers', CustomerController::class)->except(['show']);

        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');

        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('products/{product}/repository', [ProductRepositoryController::class, 'store'])
            ->name('products.repository.store');
        Route::post('products/{product}/repository/sync', [ProductRepositoryController::class, 'sync'])
            ->name('products.repository.sync');
        Route::delete('products/{product}/repository', [ProductRepositoryController::class, 'destroy'])
            ->name('products.repository.destroy');
        Route::post(
            'products/{product}/vcs-suggestions/{suggestion}/accept',
            [ProductVcsImportSuggestionController::class, 'accept'],
        )->name('products.vcs-suggestions.accept');
        Route::post(
            'products/{product}/vcs-suggestions/{suggestion}/dismiss',
            [ProductVcsImportSuggestionController::class, 'dismiss'],
        )->name('products.vcs-suggestions.dismiss');
        Route::post('products/scope-assessment/preview', [ProductScopeAssessmentController::class, 'preview'])
            ->name('products.scope-assessment.preview');
        Route::get('products/{product}/scope-assessments/latest', [ProductScopeAssessmentController::class, 'show'])
            ->name('products.scope-assessments.latest');
        Route::post('products/{product}/scope-assessments', [ProductScopeAssessmentController::class, 'store'])
            ->name('products.scope-assessments.store');
        Route::post('products/classification/preview', [ProductClassificationController::class, 'preview'])
            ->name('products.classification.preview');
        Route::get('products/{product}/classifications/latest', [ProductClassificationController::class, 'show'])
            ->name('products.classifications.latest');
        Route::post('products/{product}/classifications', [ProductClassificationController::class, 'store'])
            ->name('products.classifications.store');
        Route::get('products/{product}/requirements', [ProductRequirementController::class, 'index'])
            ->name('products.requirements.index');
        Route::get('products/{product}/requirements/{requirement}/edit', [ProductRequirementController::class, 'edit'])
            ->name('products.requirements.edit');
        Route::put('products/{product}/requirements/{requirement}', [ProductRequirementController::class, 'update'])
            ->name('products.requirements.update');
        Route::get('products/{product}/passport', [ProductCompliancePassportController::class, 'show'])
            ->name('products.passport.show');
        Route::get('products/{product}/readiness', [ProductReadinessController::class, 'show'])
            ->name('products.readiness.show');
        Route::get('products/{product}/readiness/export', [ProductReadinessController::class, 'export'])
            ->name('products.readiness.export');
        Route::resource('products.controls', ProductControlController::class)
            ->except(['show'])
            ->parameters(['controls' => 'product_control'])
            ->scoped();
        Route::resource('products.risks', ProductRiskController::class)
            ->except(['show'])
            ->scoped();
        Route::resource('products.vulnerabilities', ProductVulnerabilityController::class)
            ->except(['show'])
            ->scoped();
        Route::get(
            'products/{product}/vulnerabilities/{vulnerability}/reporting',
            [VulnerabilityReportingController::class, 'show'],
        )->name('products.vulnerabilities.reporting.show')->scopeBindings();
        Route::put(
            'products/{product}/vulnerabilities/{vulnerability}/reporting',
            [VulnerabilityReportingController::class, 'update'],
        )->name('products.vulnerabilities.reporting.update')->scopeBindings();
        Route::post(
            'products/{product}/vulnerabilities/{vulnerability}/reporting/submit-approval',
            [VulnerabilityReportingController::class, 'submitApproval'],
        )->name('products.vulnerabilities.reporting.submit-approval')->scopeBindings();
        Route::post(
            'products/{product}/vulnerabilities/{vulnerability}/reporting/approve',
            [VulnerabilityReportingController::class, 'approve'],
        )->name('products.vulnerabilities.reporting.approve')->scopeBindings();
        Route::post(
            'products/{product}/vulnerabilities/{vulnerability}/reporting/reject',
            [VulnerabilityReportingController::class, 'reject'],
        )->name('products.vulnerabilities.reporting.reject')->scopeBindings();
        Route::post(
            'products/{product}/vulnerabilities/{vulnerability}/reporting/mark-submitted',
            [VulnerabilityReportingController::class, 'markSubmitted'],
        )->name('products.vulnerabilities.reporting.mark-submitted')->scopeBindings();
        Route::post(
            'products/{product}/vulnerabilities/{vulnerability}/reporting/escalate',
            [VulnerabilityReportingController::class, 'escalate'],
        )->name('products.vulnerabilities.reporting.escalate')->scopeBindings();
        Route::get(
            'products/{product}/vulnerabilities/{vulnerability}/reporting/export',
            [VulnerabilityReportingController::class, 'export'],
        )->name('products.vulnerabilities.reporting.export')->scopeBindings();
        Route::get('products/{product}/evidence/{evidence}/download', [EvidenceController::class, 'download'])
            ->name('products.evidence.download');
        Route::resource('products.evidence', EvidenceController::class)
            ->except(['show'])
            ->scoped();
        Route::post('products/{product}/tasks/{task}/submit-approval', [TaskController::class, 'submitApproval'])
            ->name('products.tasks.submit-approval')
            ->scopeBindings();
        Route::post('products/{product}/tasks/{task}/approve', [TaskController::class, 'approve'])
            ->name('products.tasks.approve')
            ->scopeBindings();
        Route::post('products/{product}/tasks/{task}/reject', [TaskController::class, 'reject'])
            ->name('products.tasks.reject')
            ->scopeBindings();
        Route::resource('products.tasks', TaskController::class)
            ->except(['show'])
            ->scoped();
        Route::get('products/{product}/components/import', [ProductComponentController::class, 'importForm'])
            ->name('products.components.import');
        Route::post('products/{product}/components/import', [ProductComponentController::class, 'import'])
            ->name('products.components.import.store');
        Route::resource('products.components', ProductComponentController::class)
            ->except(['show'])
            ->scoped();
        Route::resource('products.versions', ProductVersionController::class)
            ->except(['show'])
            ->parameters(['versions' => 'version'])
            ->scoped();
        Route::resource('products.support-periods', ProductSupportPeriodController::class)
            ->except(['show'])
            ->parameters(['support-periods' => 'support_period'])
            ->scoped();
        Route::get('products/{product}/deployments/import/template', [ProductDeploymentController::class, 'importTemplate'])
            ->name('products.deployments.import.template');
        Route::get('products/{product}/deployments/import', [ProductDeploymentController::class, 'importForm'])
            ->name('products.deployments.import');
        Route::post('products/{product}/deployments/import', [ProductDeploymentController::class, 'import'])
            ->name('products.deployments.import.store');
        Route::get('products/{product}/deployments/unsupported', [ProductDeploymentController::class, 'unsupported'])
            ->name('products.deployments.unsupported');
        Route::resource('products.deployments', ProductDeploymentController::class)
            ->except(['show'])
            ->scoped();
        Route::post('products/{product}/campaigns/{campaign}/activate', [PatchCampaignController::class, 'activate'])
            ->name('products.campaigns.activate')
            ->scopeBindings();
        Route::post('products/{product}/campaigns/{campaign}/notify', [PatchCampaignController::class, 'notify'])
            ->name('products.campaigns.notify')
            ->scopeBindings();
        Route::get('products/{product}/campaigns/{campaign}/export', [PatchCampaignController::class, 'export'])
            ->name('products.campaigns.export')
            ->scopeBindings();
        Route::put(
            'products/{product}/campaigns/{campaign}/targets/{target}',
            [PatchCampaignController::class, 'updateTarget'],
        )
            ->name('products.campaigns.targets.update')
            ->scopeBindings();
        Route::resource('products.campaigns', PatchCampaignController::class)
            ->scoped();

        Route::prefix('internal-api')->name('internal.')->group(function () {
            Route::get('users', [UserApiController::class, 'index'])
                ->name('users.index');
            Route::get('controls', [ControlApiController::class, 'index'])
                ->name('controls.index');
            Route::get('customers', [CustomerApiController::class, 'index'])
                ->name('customers.index');
            Route::get('products', [ProductApiController::class, 'index'])
                ->name('products.index');
            Route::get('products/{product}/versions', [ProductVersionApiController::class, 'index'])
                ->name('products.versions.index');
            Route::get('products/{product}/support-periods', [ProductSupportPeriodApiController::class, 'index'])
                ->name('products.support-periods.index');
            Route::get('products/{product}/deployments', [ProductDeploymentApiController::class, 'index'])
                ->name('products.deployments.index');
            Route::get('products/{product}/campaigns', [PatchCampaignApiController::class, 'index'])
                ->name('products.campaigns.index');
            Route::get('products/{product}/requirements', [ProductRequirementApiController::class, 'index'])
                ->name('products.requirements.index');
            Route::get('products/{product}/controls', [ProductControlApiController::class, 'index'])
                ->name('products.controls.index');
            Route::get('products/{product}/risks', [ProductRiskApiController::class, 'index'])
                ->name('products.risks.index');
            Route::get('products/{product}/components', [ProductComponentApiController::class, 'index'])
                ->name('products.components.index');
            Route::get('products/{product}/vulnerabilities', [ProductVulnerabilityApiController::class, 'index'])
                ->name('products.vulnerabilities.index');
            Route::get('products/{product}/evidence', [EvidenceApiController::class, 'index'])
                ->name('products.evidence.index');
            Route::get('products/{product}/tasks', [TaskApiController::class, 'index'])
                ->name('products.tasks.index');
            Route::get('audit-logs', [AuditLogApiController::class, 'index'])
                ->name('audit-logs.index');
        });

        Route::prefix('admin')->name('admin.')->middleware('can:platform.admin')->group(function () {
            Route::resource('organizations', AdminOrganizationController::class)
                ->except(['show']);

            Route::resource('organizations.users', AdminOrganizationUserController::class)
                ->except(['show'])
                ->scoped();
            Route::post(
                'organizations/{organization}/users/{user}/reset-two-factor',
                [AdminOrganizationUserController::class, 'resetTwoFactor'],
            )->name('organizations.users.reset-two-factor');

            Route::resource('requirements', AdminRequirementController::class)
                ->except(['show', 'destroy']);

            Route::get('audit-logs', [AdminAuditLogController::class, 'index'])
                ->name('audit-logs.index');

            Route::prefix('internal-api')->name('internal.')->group(function () {
                Route::get('organizations', [OrganizationApiController::class, 'index'])
                    ->name('organizations.index');

                Route::get('organizations/{organization}/users', [OrganizationUserApiController::class, 'index'])
                    ->name('organizations.users.index');

                Route::get('requirements', [RequirementApiController::class, 'index'])
                    ->name('requirements.index');

                Route::get('audit-logs', [AdminAuditLogApiController::class, 'index'])
                    ->name('audit-logs.index');
            });
        });
    });
});

require __DIR__ . '/settings.php';

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
use App\Http\Controllers\Api\UserSecurityInstructionApiController;
use App\Http\Controllers\Api\ProductSupportPeriodApiController;
use App\Http\Controllers\Api\ProductVersionApiController;
use App\Http\Controllers\Api\ProductVulnerabilityApiController;
use App\Http\Controllers\Api\ProductIncidentApiController;
use App\Http\Controllers\Api\ProductSdlApiController;
use App\Http\Controllers\Api\IncidentApiController;
use App\Http\Controllers\Api\TaskApiController;
use App\Http\Controllers\Api\OrgPolicyApiController;
use App\Http\Controllers\Api\AuditorReviewPackageApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuditorFindingController;
use App\Http\Controllers\AuditorGuestReviewController;
use App\Http\Controllers\AuditorReviewPackageController;
use App\Http\Controllers\Auth\ForcePasswordChangeController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\ControlController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrgPolicyController;
use App\Http\Controllers\ProductClassificationController;
use App\Http\Controllers\ProductCompliancePassportController;
use App\Http\Controllers\ProductComponentController;
use App\Http\Controllers\ProductControlController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductDeploymentController;
use App\Http\Controllers\PatchCampaignController;
use App\Http\Controllers\ProductReadinessController;
use App\Http\Controllers\ProductAssistantController;
use App\Http\Controllers\ProductRepositoryController;
use App\Http\Controllers\ProductRequirementController;
use App\Http\Controllers\ProductRiskController;
use App\Http\Controllers\UserSecurityInstructionController;
use App\Http\Controllers\ProductScopeAssessmentController;
use App\Http\Controllers\ProductSupportPeriodController;
use App\Http\Controllers\ProductVersionController;
use App\Http\Controllers\ProductVcsImportSuggestionController;
use App\Http\Controllers\ProductVulnerabilityController;
use App\Http\Controllers\ProductIncidentController;
use App\Http\Controllers\ProductSdlController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TranslationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VulnerabilityReportingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('locale/{locale}', LocaleController::class)->name('locale.update');
Route::get('translations/{locale}.json', TranslationController::class)
    ->where('locale', '[a-z]{2}')
    ->middleware('throttle:60,1')
    ->name('translations.show');

Route::middleware('throttle:30,1')->group(function () {
    Route::get('auditor/guest/{token}', [AuditorGuestReviewController::class, 'show'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('auditor.guest.show');
});

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

        Route::get('incidents', [IncidentController::class, 'index'])
            ->name('incidents.index');

        Route::get('policies/template', [OrgPolicyController::class, 'template'])
            ->name('policies.template');
        Route::post('policies/{org_policy}/submit-review', [OrgPolicyController::class, 'submitReview'])
            ->name('policies.submit-review');
        Route::post('policies/{org_policy}/approve', [OrgPolicyController::class, 'approve'])
            ->name('policies.approve');
        Route::post('policies/{org_policy}/retire', [OrgPolicyController::class, 'retire'])
            ->name('policies.retire');
        Route::post('policies/{org_policy}/publish-evidence', [OrgPolicyController::class, 'publishEvidence'])
            ->name('policies.publish-evidence');
        Route::get('policies/{org_policy}/export', [OrgPolicyController::class, 'export'])
            ->name('policies.export');
        Route::resource('policies', OrgPolicyController::class)
            ->except(['show'])
            ->parameters(['policies' => 'org_policy']);

        Route::get('auditor', [AuditorReviewPackageController::class, 'index'])
            ->name('auditor.index');
        Route::get('auditor/packages/create', [AuditorReviewPackageController::class, 'create'])
            ->name('auditor.packages.create');
        Route::post('auditor/packages', [AuditorReviewPackageController::class, 'store'])
            ->name('auditor.packages.store');
        Route::get('auditor/packages/{package}', [AuditorReviewPackageController::class, 'show'])
            ->name('auditor.packages.show');
        Route::get('auditor/packages/{package}/edit', [AuditorReviewPackageController::class, 'edit'])
            ->name('auditor.packages.edit');
        Route::put('auditor/packages/{package}', [AuditorReviewPackageController::class, 'update'])
            ->name('auditor.packages.update');
        Route::delete('auditor/packages/{package}', [AuditorReviewPackageController::class, 'destroy'])
            ->name('auditor.packages.destroy');
        Route::post('auditor/packages/{package}/share', [AuditorReviewPackageController::class, 'share'])
            ->name('auditor.packages.share');
        Route::post('auditor/packages/{package}/close', [AuditorReviewPackageController::class, 'close'])
            ->name('auditor.packages.close');
        Route::post('auditor/packages/{package}/guest-link', [AuditorReviewPackageController::class, 'generateGuestLink'])
            ->name('auditor.packages.guest-link.generate');
        Route::delete('auditor/packages/{package}/guest-link', [AuditorReviewPackageController::class, 'revokeGuestLink'])
            ->name('auditor.packages.guest-link.revoke');
        Route::get('auditor/packages/{package}/export', [AuditorReviewPackageController::class, 'export'])
            ->name('auditor.packages.export');
        Route::post('auditor/packages/{package}/findings', [AuditorFindingController::class, 'store'])
            ->name('auditor.packages.findings.store');
        Route::put('auditor/packages/{package}/findings/{finding}', [AuditorFindingController::class, 'update'])
            ->name('auditor.packages.findings.update')
            ->scopeBindings();
        Route::put('auditor/packages/{package}/findings/{finding}/status', [AuditorFindingController::class, 'updateStatus'])
            ->name('auditor.packages.findings.status')
            ->scopeBindings();
        Route::delete('auditor/packages/{package}/findings/{finding}', [AuditorFindingController::class, 'destroy'])
            ->name('auditor.packages.findings.destroy')
            ->scopeBindings();

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
        Route::get('products/{product}/assistant', [ProductAssistantController::class, 'show'])
            ->name('products.assistant.show');
        Route::post('products/{product}/assistant/messages', [ProductAssistantController::class, 'storeMessage'])
            ->name('products.assistant.messages.store');
        Route::post('products/{product}/assistant/analyse', [ProductAssistantController::class, 'analyseDocument'])
            ->name('products.assistant.analyse');
        Route::post('products/{product}/assistant/draft', [ProductAssistantController::class, 'generateDraft'])
            ->name('products.assistant.draft');
        Route::post('products/{product}/assistant/triage', [ProductAssistantController::class, 'triageVulnerability'])
            ->name('products.assistant.triage');
        Route::get(
            'products/{product}/assistant/conversations/{conversation}',
            [ProductAssistantController::class, 'showConversation'],
        )->name('products.assistant.conversations.show');
        Route::resource('products.controls', ProductControlController::class)
            ->except(['show'])
            ->parameters(['controls' => 'product_control'])
            ->scoped();
        Route::resource('products.risks', ProductRiskController::class)
            ->except(['show'])
            ->scoped();
        Route::resource('products.security-instructions', UserSecurityInstructionController::class)
            ->except(['show'])
            ->parameters(['security-instructions' => 'instruction'])
            ->scoped();
        Route::get(
            'products/{product}/security-instructions-template',
            [UserSecurityInstructionController::class, 'template'],
        )->name('products.security-instructions.template');
        Route::post(
            'products/{product}/security-instructions/{instruction}/submit-review',
            [UserSecurityInstructionController::class, 'submitReview'],
        )->name('products.security-instructions.submit-review')->scopeBindings();
        Route::post(
            'products/{product}/security-instructions/{instruction}/publish',
            [UserSecurityInstructionController::class, 'publish'],
        )->name('products.security-instructions.publish')->scopeBindings();
        Route::post(
            'products/{product}/security-instructions/{instruction}/publish-evidence',
            [UserSecurityInstructionController::class, 'publishEvidence'],
        )->name('products.security-instructions.publish-evidence')->scopeBindings();
        Route::post(
            'products/{product}/security-instructions/{instruction}/retire',
            [UserSecurityInstructionController::class, 'retire'],
        )->name('products.security-instructions.retire')->scopeBindings();
        Route::post(
            'products/{product}/security-instructions/{instruction}/ai-draft',
            [UserSecurityInstructionController::class, 'suggestAiDraft'],
        )->name('products.security-instructions.ai-draft')->scopeBindings();
        Route::post(
            'products/{product}/security-instructions/{instruction}/create-pair',
            [UserSecurityInstructionController::class, 'createPair'],
        )->name('products.security-instructions.create-pair')->scopeBindings();
        Route::get(
            'products/{product}/security-instructions/{instruction}/export/{format}',
            [UserSecurityInstructionController::class, 'export'],
        )->name('products.security-instructions.export')->scopeBindings()
            ->whereIn('format', ['html', 'pdf', 'readme', 'release']);
        Route::resource('products.vulnerabilities', ProductVulnerabilityController::class)
            ->except(['show'])
            ->scoped();
        Route::resource('products.incidents', ProductIncidentController::class)
            ->except(['show'])
            ->scoped();
        Route::resource('products.sdl', ProductSdlController::class)
            ->except(['show'])
            ->parameters(['sdl' => 'sdlRun'])
            ->scoped();
        Route::get(
            'products/{product}/sdl-stage-templates',
            [ProductSdlController::class, 'stageTemplates'],
        )->name('products.sdl.stage-templates');
        Route::put(
            'products/{product}/sdl/{sdlRun}/stages/{stage}',
            [ProductSdlController::class, 'updateStage'],
        )->name('products.sdl.stages.update')->scopeBindings();
        Route::post(
            'products/{product}/sdl/{sdlRun}/approve',
            [ProductSdlController::class, 'approve'],
        )->name('products.sdl.approve')->scopeBindings();
        Route::post(
            'products/{product}/sdl/{sdlRun}/revoke-approval',
            [ProductSdlController::class, 'revokeApproval'],
        )->name('products.sdl.revoke-approval')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/timeline',
            [ProductIncidentController::class, 'storeTimeline'],
        )->name('products.incidents.timeline.store')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/reports',
            [ProductIncidentController::class, 'storeReport'],
        )->name('products.incidents.reports.store')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/ai-draft',
            [ProductIncidentController::class, 'suggestAiDraft'],
        )->name('products.incidents.ai-draft')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/communications',
            [ProductIncidentController::class, 'storeCustomerCommunication'],
        )->name('products.incidents.communications.store')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/link-vulnerability',
            [ProductIncidentController::class, 'linkVulnerability'],
        )->name('products.incidents.link-vulnerability')->scopeBindings();
        Route::delete(
            'products/{product}/incidents/{incident}/vulnerability',
            [ProductIncidentController::class, 'unlinkVulnerability'],
        )->name('products.incidents.unlink-vulnerability')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/create-vulnerability',
            [ProductIncidentController::class, 'createVulnerability'],
        )->name('products.incidents.create-vulnerability')->scopeBindings();
        Route::post(
            'products/{product}/incidents/{incident}/close',
            [ProductIncidentController::class, 'close'],
        )->name('products.incidents.close')->scopeBindings();
        Route::get(
            'products/{product}/incidents/{incident}/export/{format}',
            [ProductIncidentController::class, 'export'],
        )->name('products.incidents.export')->scopeBindings()
            ->whereIn('format', ['markdown', 'pdf']);
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
            Route::get('incidents', [IncidentApiController::class, 'index'])
                ->name('incidents.index');
            Route::get('policies', [OrgPolicyApiController::class, 'index'])
                ->name('policies.index');
            Route::get('auditor/packages', [AuditorReviewPackageApiController::class, 'index'])
                ->name('auditor.packages.index');
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
            Route::get('products/{product}/security-instructions', [UserSecurityInstructionApiController::class, 'index'])
                ->name('products.security-instructions.index');
            Route::get('products/{product}/components', [ProductComponentApiController::class, 'index'])
                ->name('products.components.index');
            Route::get('products/{product}/vulnerabilities', [ProductVulnerabilityApiController::class, 'index'])
                ->name('products.vulnerabilities.index');
            Route::get('products/{product}/incidents', [ProductIncidentApiController::class, 'index'])
                ->name('products.incidents.index');
            Route::get('products/{product}/sdl', [ProductSdlApiController::class, 'index'])
                ->name('products.sdl.index');
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

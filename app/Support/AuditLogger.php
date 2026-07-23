<?php

namespace App\Support;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Evidence;
use App\Models\IncidentCustomerCommunication;
use App\Models\IncidentReport;
use App\Models\IncidentTimelineEvent;
use App\Models\OrganizationVcsConnection;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\PatchCampaign;
use App\Models\PatchCampaignTarget;
use App\Models\ProductDeployment;
use App\Models\ProductIncident;
use App\Models\ProductRepository;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\SdlException;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\Task;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Models\VcsImportSuggestion;
use App\Models\VcsSyncRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'github_private_key',
        'private_key',
        'recovery_code',
        'code',
        'otp',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'prompt',
        'api_key',
        'context',
        'messages',
    ];

    public static function resolveSource(?Request $request = null): AuditEventSource
    {
        $request ??= request();

        if ($request->is('api/*')) {
            return AuditEventSource::Api;
        }

        return AuditEventSource::Workspace;
    }

    public static function logLoginSuccess(User $user, ?AuditEventSource $source = null): void
    {
        self::persist(
            type: AuditEventType::LoginSuccess,
            success: true,
            source: $source ?? self::resolveSource(),
            actor: $user,
            details: [
                ['field' => 'email', 'value' => $user->email],
            ],
        );
    }

    public static function logLoginFailed(
        string $email,
        string $reason,
        ?User $user = null,
        ?AuditEventSource $source = null,
    ): void {
        self::persist(
            type: AuditEventType::LoginFailed,
            success: false,
            source: $source ?? self::resolveSource(),
            actor: $user,
            email: $email,
            name: $user?->name,
            details: [
                ['field' => 'email', 'value' => $email],
                ['field' => 'reason', 'value' => $reason],
            ],
        );
    }

    public static function logTwoFactorChallengeSuccess(User $user, ?AuditEventSource $source = null): void
    {
        self::persist(
            type: AuditEventType::TwoFactorChallengeSuccess,
            success: true,
            source: $source ?? self::resolveSource(),
            actor: $user,
            details: [
                ['field' => 'email', 'value' => $user->email],
            ],
        );
    }

    public static function logTwoFactorChallengeFailed(User $user, ?AuditEventSource $source = null): void
    {
        self::persist(
            type: AuditEventType::TwoFactorChallengeFailed,
            success: false,
            source: $source ?? self::resolveSource(),
            actor: $user,
            details: [
                ['field' => 'email', 'value' => $user->email],
                ['field' => 'reason', 'value' => 'invalid_mfa_code'],
            ],
        );
    }

    public static function logTwoFactorReset(
        User $target,
        User $actor,
        int $organizationId,
        ?AuditEventSource $source = null,
    ): void {
        self::persist(
            type: AuditEventType::TwoFactorReset,
            success: true,
            source: $source ?? self::resolveSource(),
            actor: $actor,
            organizationId: $organizationId,
            details: [
                ['field' => 'target_user_id', 'value' => (string) $target->id],
                ['field' => 'target_email', 'value' => $target->email],
            ],
        );
    }

    public static function logProductCreated(Product $product, User $actor): void
    {
        self::persist(
            type: AuditEventType::ProductCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $product->organization_id,
            productId: $product->id,
            details: [
                ['field' => 'product_id', 'value' => (string) $product->id],
                ['field' => 'name', 'value' => $product->name],
                ['field' => 'slug', 'value' => $product->slug],
            ],
        );
    }

    public static function logProductUpdated(Product $product, User $actor): void
    {
        self::persist(
            type: AuditEventType::ProductUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $product->organization_id,
            productId: $product->id,
            details: [
                ['field' => 'product_id', 'value' => (string) $product->id],
                ['field' => 'name', 'value' => $product->name],
                ['field' => 'slug', 'value' => $product->slug],
            ],
        );
    }

    public static function logProductDeleted(Product $product, User $actor): void
    {
        self::persist(
            type: AuditEventType::ProductDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $product->organization_id,
            productId: $product->id,
            details: [
                ['field' => 'product_id', 'value' => (string) $product->id],
                ['field' => 'name', 'value' => $product->name],
                ['field' => 'slug', 'value' => $product->slug],
            ],
        );
    }

    public static function logCustomerCreated(Customer $customer, User $actor): void
    {
        self::persist(
            type: AuditEventType::CustomerCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $customer->organization_id,
            details: [
                ['field' => 'customer_id', 'value' => (string) $customer->id],
                ['field' => 'name', 'value' => $customer->name],
                ['field' => 'criticality', 'value' => $customer->criticality->value],
            ],
        );
    }

    public static function logCustomerUpdated(Customer $customer, User $actor): void
    {
        self::persist(
            type: AuditEventType::CustomerUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $customer->organization_id,
            details: [
                ['field' => 'customer_id', 'value' => (string) $customer->id],
                ['field' => 'name', 'value' => $customer->name],
                ['field' => 'criticality', 'value' => $customer->criticality->value],
                ['field' => 'is_active', 'value' => $customer->is_active ? '1' : '0'],
            ],
        );
    }

    public static function logCustomerDeleted(Customer $customer, User $actor): void
    {
        self::persist(
            type: AuditEventType::CustomerDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $customer->organization_id,
            details: [
                ['field' => 'customer_id', 'value' => (string) $customer->id],
                ['field' => 'name', 'value' => $customer->name],
            ],
        );
    }

    public static function logUserSecurityInstructionCreated(
        UserSecurityInstruction $instruction,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
                ['field' => 'locale', 'value' => $instruction->locale],
            ],
        );
    }

    public static function logUserSecurityInstructionUpdated(
        UserSecurityInstruction $instruction,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
                ['field' => 'status', 'value' => $instruction->status->value],
            ],
        );
    }

    public static function logUserSecurityInstructionDeleted(
        UserSecurityInstruction $instruction,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
            ],
        );
    }

    public static function logUserSecurityInstructionSubmitted(
        UserSecurityInstruction $instruction,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionSubmitted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
            ],
        );
    }

    public static function logUserSecurityInstructionPublished(
        UserSecurityInstruction $instruction,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionPublished,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
                ['field' => 'version_label', 'value' => $instruction->version_label],
                ['field' => 'locale', 'value' => $instruction->locale],
            ],
        );
    }

    public static function logUserSecurityInstructionRetired(
        UserSecurityInstruction $instruction,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionRetired,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
            ],
        );
    }

    public static function logUserSecurityInstructionExported(
        UserSecurityInstruction $instruction,
        User $actor,
        string $format,
        ?Customer $customer = null,
        ?ProductDeployment $deployment = null,
    ): void {
        $details = [
            ['field' => 'instruction_id', 'value' => (string) $instruction->id],
            ['field' => 'title', 'value' => $instruction->title],
            ['field' => 'format', 'value' => $format],
        ];

        if ($customer !== null) {
            $details[] = ['field' => 'customer_id', 'value' => (string) $customer->id];
            $details[] = ['field' => 'customer_name', 'value' => $customer->name];
        }

        if ($deployment !== null) {
            $details[] = ['field' => 'deployment_id', 'value' => (string) $deployment->id];
        }

        self::persist(
            type: AuditEventType::UserSecurityInstructionExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: $details,
        );
    }

    public static function logUserSecurityInstructionPublishedEvidence(
        UserSecurityInstruction $instruction,
        Evidence $evidence,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::UserSecurityInstructionPublishedEvidence,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $evidence->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'title', 'value' => $instruction->title],
                ['field' => 'evidence_id', 'value' => (string) $evidence->id],
                ['field' => 'locale', 'value' => $instruction->locale],
                ['field' => 'version_label', 'value' => $instruction->version_label],
            ],
        );
    }

    public static function logIncidentCreated(ProductIncident $incident, User $actor): void
    {
        self::persist(
            type: AuditEventType::IncidentCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'title', 'value' => $incident->title],
                ['field' => 'status', 'value' => $incident->status->value],
                ['field' => 'severity', 'value' => $incident->severity->value],
            ],
        );
    }

    public static function logIncidentUpdated(ProductIncident $incident, User $actor): void
    {
        self::persist(
            type: AuditEventType::IncidentUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'title', 'value' => $incident->title],
                ['field' => 'status', 'value' => $incident->status->value],
            ],
        );
    }

    public static function logIncidentDeleted(ProductIncident $incident, User $actor): void
    {
        self::persist(
            type: AuditEventType::IncidentDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'title', 'value' => $incident->title],
            ],
        );
    }

    public static function logSdlRunCreated(SdlRun $run, User $actor): void
    {
        self::persist(
            type: AuditEventType::SdlRunCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'title', 'value' => $run->title],
                ['field' => 'status', 'value' => $run->status->value],
                ['field' => 'current_stage', 'value' => $run->current_stage->value],
            ],
        );
    }

    public static function logSdlRunUpdated(SdlRun $run, User $actor): void
    {
        self::persist(
            type: AuditEventType::SdlRunUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'title', 'value' => $run->title],
                ['field' => 'status', 'value' => $run->status->value],
                ['field' => 'current_stage', 'value' => $run->current_stage->value],
            ],
        );
    }

    public static function logSdlRunDeleted(SdlRun $run, User $actor): void
    {
        self::persist(
            type: AuditEventType::SdlRunDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'title', 'value' => $run->title],
            ],
        );
    }

    public static function logSdlStageUpdated(
        SdlRun $run,
        SdlStageEntry $entry,
        User $actor,
        string $previousStatus,
    ): void {
        self::persist(
            type: AuditEventType::SdlStageUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'stage', 'value' => $entry->stage->value],
                ['field' => 'previous_status', 'value' => $previousStatus],
                ['field' => 'status', 'value' => $entry->status->value],
            ],
        );
    }

    public static function logSdlRunApproved(SdlRun $run, User $actor): void
    {
        self::persist(
            type: AuditEventType::SdlRunApproved,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'title', 'value' => $run->title],
                ['field' => 'approved_at', 'value' => $run->approved_at?->toIso8601String() ?? ''],
            ],
        );
    }

    public static function logSdlRunApprovalRevoked(SdlRun $run, User $actor): void
    {
        self::persist(
            type: AuditEventType::SdlRunApprovalRevoked,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'title', 'value' => $run->title],
            ],
        );
    }

    public static function logSdlRunExported(
        SdlRun $run,
        User $actor,
        string $format,
    ): void {
        self::persist(
            type: AuditEventType::SdlRunExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'title', 'value' => $run->title],
                ['field' => 'format', 'value' => $format],
            ],
        );
    }

    public static function logSdlExceptionRecorded(
        SdlRun $run,
        SdlStageEntry $entry,
        SdlException $exception,
        User $actor,
        ?int $taskId = null,
    ): void {
        self::persist(
            type: AuditEventType::SdlExceptionRecorded,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'stage', 'value' => $entry->stage->value],
                ['field' => 'exception_id', 'value' => (string) $exception->id],
                ['field' => 'owner_user_id', 'value' => (string) $exception->owner_user_id],
                ['field' => 'expires_at', 'value' => $exception->expires_at->toDateString()],
                ['field' => 'task_id', 'value' => $taskId !== null ? (string) $taskId : ''],
            ],
        );
    }

    public static function logSdlExceptionCleared(
        SdlRun $run,
        SdlStageEntry $entry,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::SdlExceptionCleared,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'stage', 'value' => $entry->stage->value],
            ],
        );
    }

    public static function logTechnicalDocumentationCreated(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'locale', 'value' => $package->locale],
            ],
        );
    }

    public static function logTechnicalDocumentationUpdated(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'status', 'value' => $package->status->value],
            ],
        );
    }

    public static function logTechnicalDocumentationDeleted(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    /**
     * @param  list<string>  $refreshed
     * @param  list<string>  $skipped
     */
    public static function logTechnicalDocumentationGeneratedRefreshed(
        TechnicalDocumentationPackage $package,
        User $actor,
        array $refreshed,
        array $skipped,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationGeneratedRefreshed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'refreshed', 'value' => implode(',', $refreshed)],
                ['field' => 'skipped', 'value' => implode(',', $skipped)],
                ['field' => 'refreshed_count', 'value' => (string) count($refreshed)],
            ],
        );
    }

    public static function logTechnicalDocumentationSubmitted(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationSubmitted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    public static function logTechnicalDocumentationPublished(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationPublished,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'version_label', 'value' => $package->version_label],
                ['field' => 'locale', 'value' => $package->locale],
            ],
        );
    }

    public static function logTechnicalDocumentationRetired(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::TechnicalDocumentationRetired,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    public static function logIncidentStatusUpdated(
        ProductIncident $incident,
        User $actor,
        string $previousStatus,
    ): void {
        self::persist(
            type: AuditEventType::IncidentStatusUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'previous_status', 'value' => $previousStatus],
                ['field' => 'status', 'value' => $incident->status->value],
            ],
        );
    }

    public static function logIncidentClosed(
        ProductIncident $incident,
        User $actor,
        string $previousStatus,
    ): void {
        self::persist(
            type: AuditEventType::IncidentClosed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'previous_status', 'value' => $previousStatus],
                ['field' => 'status', 'value' => $incident->status->value],
                ['field' => 'closed_by', 'value' => (string) ($incident->closed_by ?? $actor->id)],
            ],
        );
    }

    public static function logIncidentExported(
        ProductIncident $incident,
        User $actor,
        string $format,
    ): void {
        self::persist(
            type: AuditEventType::IncidentExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'title', 'value' => $incident->title],
                ['field' => 'format', 'value' => $format],
            ],
        );
    }

    public static function logIncidentReportAdded(
        ProductIncident $incident,
        IncidentReport $report,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::IncidentReportAdded,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'report_id', 'value' => (string) $report->id],
                ['field' => 'authority', 'value' => $report->authority],
                ['field' => 'submission_channel', 'value' => $report->submission_channel->value],
            ],
        );
    }

    public static function logIncidentCustomerCommunicationAdded(
        ProductIncident $incident,
        IncidentCustomerCommunication $communication,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::IncidentCustomerCommunicationAdded,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'communication_id', 'value' => (string) $communication->id],
                ['field' => 'subject', 'value' => $communication->subject],
                ['field' => 'channel', 'value' => $communication->channel->value],
            ],
        );
    }

    public static function logIncidentTimelineEventAdded(
        ProductIncident $incident,
        IncidentTimelineEvent $event,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::IncidentTimelineEventAdded,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'timeline_event_id', 'value' => (string) $event->id],
                ['field' => 'label', 'value' => $event->label],
            ],
        );
    }

    public static function logOrgPolicyCreated(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicyCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
                ['field' => 'policy_type', 'value' => $policy->policy_type->value],
            ],
        );
    }

    public static function logOrgPolicyUpdated(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicyUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
                ['field' => 'status', 'value' => $policy->status->value],
            ],
        );
    }

    public static function logOrgPolicyDeleted(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicyDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
            ],
        );
    }

    public static function logOrgPolicySubmitted(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicySubmitted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
            ],
        );
    }

    public static function logOrgPolicyApproved(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicyApproved,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
                ['field' => 'policy_type', 'value' => $policy->policy_type->value],
            ],
        );
    }

    public static function logOrgPolicyRetired(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicyRetired,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
            ],
        );
    }

    public static function logOrgPolicyPublishedEvidence(
        OrgPolicy $policy,
        Evidence $evidence,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::OrgPolicyPublishedEvidence,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            productId: $evidence->product_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
                ['field' => 'evidence_id', 'value' => (string) $evidence->id],
                ['field' => 'policy_type', 'value' => $policy->policy_type->value],
            ],
        );
    }

    public static function logOrgPolicyExported(OrgPolicy $policy, User $actor): void
    {
        self::persist(
            type: AuditEventType::OrgPolicyExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $policy->organization_id,
            details: [
                ['field' => 'policy_id', 'value' => (string) $policy->id],
                ['field' => 'title', 'value' => $policy->title],
                ['field' => 'policy_type', 'value' => $policy->policy_type->value],
                ['field' => 'version_label', 'value' => $policy->version_label],
            ],
        );
    }

    public static function logAuditorPackageCreated(AuditorReviewPackage $package, User $actor): void
    {
        self::persist(
            type: AuditEventType::AuditorPackageCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'product_id', 'value' => (string) $package->product_id],
            ],
        );
    }

    public static function logAuditorPackageUpdated(AuditorReviewPackage $package, User $actor): void
    {
        self::persist(
            type: AuditEventType::AuditorPackageUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'status', 'value' => $package->status->value],
            ],
        );
    }

    public static function logAuditorPackageDeleted(AuditorReviewPackage $package, User $actor): void
    {
        self::persist(
            type: AuditEventType::AuditorPackageDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    public static function logAuditorPackageShared(AuditorReviewPackage $package, User $actor): void
    {
        self::persist(
            type: AuditEventType::AuditorPackageShared,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    public static function logAuditorPackageNotificationsQueued(
        AuditorReviewPackage $package,
        User $actor,
        int $queued,
        int $skippedNoEmail,
    ): void {
        self::persist(
            type: AuditEventType::AuditorPackageNotificationsQueued,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'queued', 'value' => (string) $queued],
                ['field' => 'skipped_no_email', 'value' => (string) $skippedNoEmail],
            ],
        );
    }

    public static function logAuditorPackageGuestLinkGenerated(
        AuditorReviewPackage $package,
        User $actor,
        string $expiresAtIso,
    ): void {
        self::persist(
            type: AuditEventType::AuditorPackageGuestLinkGenerated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'expires_at', 'value' => $expiresAtIso],
            ],
        );
    }

    public static function logAuditorPackageGuestLinkRevoked(
        AuditorReviewPackage $package,
        User $actor,
    ): void {
        self::persist(
            type: AuditEventType::AuditorPackageGuestLinkRevoked,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    public static function logAuditorPackageClosed(AuditorReviewPackage $package, User $actor): void
    {
        self::persist(
            type: AuditEventType::AuditorPackageClosed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
            ],
        );
    }

    public static function logAuditorPackageExported(AuditorReviewPackage $package, User $actor): void
    {
        self::persist(
            type: AuditEventType::AuditorPackageExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package->organization_id,
            productId: $package->product_id,
            details: [
                ['field' => 'package_id', 'value' => (string) $package->id],
                ['field' => 'title', 'value' => $package->title],
                ['field' => 'format', 'value' => 'zip'],
            ],
        );
    }

    public static function logAuditorFindingCreated(AuditorFinding $finding, User $actor): void
    {
        $package = $finding->package;

        self::persist(
            type: AuditEventType::AuditorFindingCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package?->organization_id,
            productId: $package?->product_id,
            details: [
                ['field' => 'finding_id', 'value' => (string) $finding->id],
                ['field' => 'package_id', 'value' => (string) $finding->package_id],
                ['field' => 'title', 'value' => $finding->title],
                ['field' => 'severity', 'value' => $finding->severity->value],
            ],
        );
    }

    public static function logAuditorFindingUpdated(AuditorFinding $finding, User $actor): void
    {
        $package = $finding->package;

        self::persist(
            type: AuditEventType::AuditorFindingUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package?->organization_id,
            productId: $package?->product_id,
            details: [
                ['field' => 'finding_id', 'value' => (string) $finding->id],
                ['field' => 'package_id', 'value' => (string) $finding->package_id],
                ['field' => 'title', 'value' => $finding->title],
                ['field' => 'severity', 'value' => $finding->severity->value],
            ],
        );
    }

    public static function logAuditorFindingDeleted(AuditorFinding $finding, User $actor): void
    {
        $package = $finding->package;

        self::persist(
            type: AuditEventType::AuditorFindingDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package?->organization_id,
            productId: $package?->product_id,
            details: [
                ['field' => 'finding_id', 'value' => (string) $finding->id],
                ['field' => 'package_id', 'value' => (string) $finding->package_id],
                ['field' => 'title', 'value' => $finding->title],
            ],
        );
    }

    public static function logAuditorFindingStatusUpdated(AuditorFinding $finding, User $actor): void
    {
        $package = $finding->package;

        self::persist(
            type: AuditEventType::AuditorFindingStatusUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $package?->organization_id,
            productId: $package?->product_id,
            details: [
                ['field' => 'finding_id', 'value' => (string) $finding->id],
                ['field' => 'package_id', 'value' => (string) $finding->package_id],
                ['field' => 'status', 'value' => $finding->status->value],
            ],
        );
    }

    public static function logDeploymentCreated(ProductDeployment $deployment, User $actor): void
    {
        self::persist(
            type: AuditEventType::DeploymentCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $deployment->organization_id,
            productId: $deployment->product_id,
            details: [
                ['field' => 'deployment_id', 'value' => (string) $deployment->id],
                ['field' => 'customer_id', 'value' => (string) $deployment->customer_id],
                ['field' => 'environment', 'value' => $deployment->environment->value],
            ],
        );
    }

    public static function logDeploymentUpdated(ProductDeployment $deployment, User $actor): void
    {
        self::persist(
            type: AuditEventType::DeploymentUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $deployment->organization_id,
            productId: $deployment->product_id,
            details: [
                ['field' => 'deployment_id', 'value' => (string) $deployment->id],
                ['field' => 'customer_id', 'value' => (string) $deployment->customer_id],
                ['field' => 'environment', 'value' => $deployment->environment->value],
            ],
        );
    }

    public static function logDeploymentDeleted(ProductDeployment $deployment, User $actor): void
    {
        self::persist(
            type: AuditEventType::DeploymentDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $deployment->organization_id,
            productId: $deployment->product_id,
            details: [
                ['field' => 'deployment_id', 'value' => (string) $deployment->id],
                ['field' => 'customer_id', 'value' => (string) $deployment->customer_id],
                ['field' => 'environment', 'value' => $deployment->environment->value],
            ],
        );
    }

    public static function logPatchCampaignCreated(PatchCampaign $campaign, User $actor): void
    {
        self::persist(
            type: AuditEventType::PatchCampaignCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
                ['field' => 'status', 'value' => $campaign->status->value],
                ['field' => 'target_version_id', 'value' => (string) $campaign->target_version_id],
            ],
        );
    }

    public static function logPatchCampaignUpdated(PatchCampaign $campaign, User $actor): void
    {
        self::persist(
            type: AuditEventType::PatchCampaignUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
                ['field' => 'status', 'value' => $campaign->status->value],
                ['field' => 'target_version_id', 'value' => (string) $campaign->target_version_id],
            ],
        );
    }

    public static function logPatchCampaignActivated(
        PatchCampaign $campaign,
        User $actor,
        int $targetsSeeded,
    ): void {
        self::persist(
            type: AuditEventType::PatchCampaignActivated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
                ['field' => 'target_version_id', 'value' => (string) $campaign->target_version_id],
                ['field' => 'targets_seeded', 'value' => (string) $targetsSeeded],
            ],
        );
    }

    public static function logPatchCampaignCompleted(PatchCampaign $campaign, User $actor): void
    {
        self::persist(
            type: AuditEventType::PatchCampaignCompleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
                ['field' => 'targets_count', 'value' => (string) $campaign->targets()->count()],
            ],
        );
    }

    public static function logPatchCampaignExported(
        PatchCampaign $campaign,
        User $actor,
        int $rowCount,
    ): void {
        self::persist(
            type: AuditEventType::PatchCampaignExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
                ['field' => 'row_count', 'value' => (string) $rowCount],
            ],
        );
    }

    public static function logPatchCampaignNotificationsQueued(
        PatchCampaign $campaign,
        User $actor,
        int $queued,
        int $skippedNoEmail,
    ): void {
        self::persist(
            type: AuditEventType::PatchCampaignNotificationsQueued,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
                ['field' => 'queued', 'value' => (string) $queued],
                ['field' => 'skipped_no_email', 'value' => (string) $skippedNoEmail],
            ],
        );
    }

    public static function logPatchCampaignDeleted(PatchCampaign $campaign, User $actor): void
    {
        self::persist(
            type: AuditEventType::PatchCampaignDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $campaign->organization_id,
            productId: $campaign->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $campaign->id],
                ['field' => 'title', 'value' => $campaign->title],
            ],
        );
    }

    public static function logCampaignTargetUpdated(
        PatchCampaignTarget $target,
        User $actor,
        string $previousStatus,
    ): void {
        $target->loadMissing(['campaign', 'deployment']);

        self::persist(
            type: AuditEventType::CampaignTargetUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $target->campaign?->organization_id,
            productId: $target->campaign?->product_id,
            details: [
                ['field' => 'campaign_id', 'value' => (string) $target->campaign_id],
                ['field' => 'target_id', 'value' => (string) $target->id],
                ['field' => 'deployment_id', 'value' => (string) $target->deployment_id],
                ['field' => 'previous_status', 'value' => $previousStatus],
                ['field' => 'status', 'value' => $target->status->value],
            ],
        );
    }

    public static function logRiskCreated(ProductRisk $risk, User $actor): void
    {
        self::persist(
            type: AuditEventType::RiskCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $risk->product->organization_id,
            productId: $risk->product_id,
            details: [
                ['field' => 'risk_id', 'value' => (string) $risk->id],
                ['field' => 'title', 'value' => $risk->title],
            ],
        );
    }

    public static function logRiskUpdated(ProductRisk $risk, User $actor): void
    {
        self::persist(
            type: AuditEventType::RiskUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $risk->product->organization_id,
            productId: $risk->product_id,
            details: [
                ['field' => 'risk_id', 'value' => (string) $risk->id],
                ['field' => 'title', 'value' => $risk->title],
                ['field' => 'status', 'value' => $risk->status->value],
            ],
        );
    }

    public static function logRiskDeleted(ProductRisk $risk, User $actor): void
    {
        $organizationId = $risk->product?->organization_id
            ?? Product::query()->whereKey($risk->product_id)->value('organization_id');

        self::persist(
            type: AuditEventType::RiskDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $organizationId !== null ? (int) $organizationId : null,
            productId: $risk->product_id,
            details: [
                ['field' => 'risk_id', 'value' => (string) $risk->id],
                ['field' => 'title', 'value' => $risk->title],
            ],
        );
    }

    public static function logEvidenceCreated(Evidence $evidence, User $actor): void
    {
        self::persist(
            type: AuditEventType::EvidenceCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $evidence->organization_id,
            productId: $evidence->product_id,
            details: [
                ['field' => 'evidence_id', 'value' => (string) $evidence->id],
                ['field' => 'title', 'value' => $evidence->title],
                ['field' => 'type', 'value' => $evidence->type->value],
            ],
        );
    }

    public static function logEvidenceUpdated(Evidence $evidence, User $actor): void
    {
        self::persist(
            type: AuditEventType::EvidenceUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $evidence->organization_id,
            productId: $evidence->product_id,
            details: [
                ['field' => 'evidence_id', 'value' => (string) $evidence->id],
                ['field' => 'title', 'value' => $evidence->title],
                ['field' => 'type', 'value' => $evidence->type->value],
            ],
        );
    }

    public static function logEvidenceDeleted(Evidence $evidence, User $actor): void
    {
        self::persist(
            type: AuditEventType::EvidenceDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $evidence->organization_id,
            productId: $evidence->product_id,
            details: [
                ['field' => 'evidence_id', 'value' => (string) $evidence->id],
                ['field' => 'title', 'value' => $evidence->title],
            ],
        );
    }

    public static function logTaskCreated(Task $task, User $actor): void
    {
        self::persist(
            type: AuditEventType::TaskCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $task->organization_id,
            productId: $task->product_id,
            details: [
                ['field' => 'task_id', 'value' => (string) $task->id],
                ['field' => 'title', 'value' => $task->title],
            ],
        );
    }

    public static function logTaskUpdated(Task $task, User $actor): void
    {
        self::persist(
            type: AuditEventType::TaskUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $task->organization_id,
            productId: $task->product_id,
            details: [
                ['field' => 'task_id', 'value' => (string) $task->id],
                ['field' => 'title', 'value' => $task->title],
                ['field' => 'status', 'value' => $task->status->value],
            ],
        );
    }

    public static function logTaskDeleted(Task $task, User $actor): void
    {
        self::persist(
            type: AuditEventType::TaskDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $task->organization_id,
            productId: $task->product_id,
            details: [
                ['field' => 'task_id', 'value' => (string) $task->id],
                ['field' => 'title', 'value' => $task->title],
            ],
        );
    }

    public static function logTaskApproved(Task $task, User $actor, ?string $comment = null): void
    {
        self::persist(
            type: AuditEventType::TaskApproved,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $task->organization_id,
            productId: $task->product_id,
            details: [
                ['field' => 'task_id', 'value' => (string) $task->id],
                ['field' => 'product_id', 'value' => (string) $task->product_id],
                ['field' => 'title', 'value' => $task->title],
                ['field' => 'comment', 'value' => $comment],
            ],
        );
    }

    public static function logTaskRejected(Task $task, User $actor, ?string $comment = null): void
    {
        self::persist(
            type: AuditEventType::TaskRejected,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $task->organization_id,
            productId: $task->product_id,
            details: [
                ['field' => 'task_id', 'value' => (string) $task->id],
                ['field' => 'product_id', 'value' => (string) $task->product_id],
                ['field' => 'title', 'value' => $task->title],
                ['field' => 'comment', 'value' => $comment],
            ],
        );
    }

    public static function logReadinessReportViewed(Product $product, User $actor): void
    {
        self::persist(
            type: AuditEventType::ReadinessReportViewed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $product->organization_id,
            productId: $product->id,
            details: [
                ['field' => 'product_id', 'value' => (string) $product->id],
                ['field' => 'name', 'value' => $product->name],
            ],
        );
    }

    /**
     * Log a completed AI assistant turn without prompt/response/context text.
     *
     * @param  array{
     *     provider: string,
     *     model: string|null,
     *     has_context: bool,
     *     context_chars: int
     * }  $meta
     */
    public static function logAiRequestCompleted(
        AiConversation $conversation,
        User $actor,
        AiMessage $userMessage,
        AiMessage $assistantMessage,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiRequestCompleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $conversation->organization_id,
            productId: $conversation->product_id,
            details: [
                ['field' => 'conversation_id', 'value' => (string) $conversation->id],
                ['field' => 'user_message_id', 'value' => (string) $userMessage->id],
                ['field' => 'assistant_message_id', 'value' => (string) $assistantMessage->id],
                ['field' => 'context_type', 'value' => $conversation->context_type->value],
                ['field' => 'provider', 'value' => $meta['provider']],
                ['field' => 'model', 'value' => $meta['model'] ?? ''],
                ['field' => 'has_context', 'value' => $meta['has_context'] ? '1' : '0'],
                ['field' => 'context_chars', 'value' => (string) $meta['context_chars']],
                ['field' => 'rag_hits', 'value' => (string) ($meta['rag_hits'] ?? 0)],
            ],
        );
    }

    public static function logAiDocumentAnalysed(
        AiConversation $conversation,
        User $actor,
        AiMessage $userMessage,
        AiMessage $assistantMessage,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiDocumentAnalysed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $conversation->organization_id,
            productId: $conversation->product_id,
            details: [
                ['field' => 'conversation_id', 'value' => (string) $conversation->id],
                ['field' => 'user_message_id', 'value' => (string) $userMessage->id],
                ['field' => 'assistant_message_id', 'value' => (string) $assistantMessage->id],
                ['field' => 'provider', 'value' => (string) ($meta['provider'] ?? '')],
                ['field' => 'model', 'value' => (string) ($meta['model'] ?? '')],
                ['field' => 'filename', 'value' => (string) ($meta['filename'] ?? '')],
                ['field' => 'document_chars', 'value' => (string) ($meta['document_chars'] ?? 0)],
                ['field' => 'suggestions_parsed', 'value' => !empty($meta['suggestions_parsed']) ? '1' : '0'],
                ['field' => 'requirement_mappings', 'value' => (string) ($meta['requirement_mappings'] ?? 0)],
                ['field' => 'evidence_mappings', 'value' => (string) ($meta['evidence_mappings'] ?? 0)],
                ['field' => 'gaps', 'value' => (string) ($meta['gaps'] ?? 0)],
            ],
        );
    }

    public static function logAiDraftGenerated(
        AiConversation $conversation,
        User $actor,
        AiMessage $userMessage,
        AiMessage $assistantMessage,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiDraftGenerated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $conversation->organization_id,
            productId: $conversation->product_id,
            details: [
                ['field' => 'conversation_id', 'value' => (string) $conversation->id],
                ['field' => 'user_message_id', 'value' => (string) $userMessage->id],
                ['field' => 'assistant_message_id', 'value' => (string) $assistantMessage->id],
                ['field' => 'provider', 'value' => (string) ($meta['provider'] ?? '')],
                ['field' => 'model', 'value' => (string) ($meta['model'] ?? '')],
                ['field' => 'campaign_id', 'value' => (string) ($meta['campaign_id'] ?? '')],
                ['field' => 'draft_type', 'value' => (string) ($meta['draft_type'] ?? '')],
                ['field' => 'draft_parsed', 'value' => !empty($meta['draft_parsed']) ? '1' : '0'],
                ['field' => 'highlights', 'value' => (string) ($meta['highlights'] ?? 0)],
                ['field' => 'recommended_actions', 'value' => (string) ($meta['recommended_actions'] ?? 0)],
            ],
        );
    }

    public static function logAiUsiSectionDraftSuggested(
        UserSecurityInstruction $instruction,
        User $actor,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiUsiSectionDraftSuggested,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $instruction->organization_id,
            productId: $instruction->product_id,
            details: [
                ['field' => 'instruction_id', 'value' => (string) $instruction->id],
                ['field' => 'section_key', 'value' => (string) ($meta['section_key'] ?? '')],
                ['field' => 'provider', 'value' => (string) ($meta['provider'] ?? '')],
                ['field' => 'model', 'value' => (string) ($meta['model'] ?? '')],
                ['field' => 'draft_parsed', 'value' => !empty($meta['draft_parsed']) ? '1' : '0'],
                ['field' => 'locale', 'value' => (string) ($meta['locale'] ?? '')],
            ],
        );
    }

    public static function logAiIncidentSummaryDraftSuggested(
        ProductIncident $incident,
        User $actor,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiIncidentSummaryDraftSuggested,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $incident->organization_id,
            productId: $incident->product_id,
            details: [
                ['field' => 'incident_id', 'value' => (string) $incident->id],
                ['field' => 'provider', 'value' => (string) ($meta['provider'] ?? '')],
                ['field' => 'model', 'value' => (string) ($meta['model'] ?? '')],
                ['field' => 'draft_parsed', 'value' => !empty($meta['draft_parsed']) ? '1' : '0'],
                ['field' => 'locale', 'value' => (string) ($meta['locale'] ?? '')],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function logAiSdlStageNotesDraftSuggested(
        SdlRun $run,
        User $actor,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiSdlStageNotesDraftSuggested,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $run->organization_id,
            productId: $run->product_id,
            details: [
                ['field' => 'sdl_run_id', 'value' => (string) $run->id],
                ['field' => 'stage', 'value' => (string) ($meta['stage'] ?? '')],
                ['field' => 'provider', 'value' => (string) ($meta['provider'] ?? '')],
                ['field' => 'model', 'value' => (string) ($meta['model'] ?? '')],
                ['field' => 'draft_parsed', 'value' => !empty($meta['draft_parsed']) ? '1' : '0'],
                ['field' => 'locale', 'value' => (string) ($meta['locale'] ?? '')],
            ],
        );
    }

    public static function logAiVulnerabilityTriageSuggested(
        AiConversation $conversation,
        User $actor,
        AiMessage $userMessage,
        AiMessage $assistantMessage,
        array $meta,
    ): void {
        self::persist(
            type: AuditEventType::AiVulnerabilityTriageSuggested,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $conversation->organization_id,
            productId: $conversation->product_id,
            details: [
                ['field' => 'conversation_id', 'value' => (string) $conversation->id],
                ['field' => 'user_message_id', 'value' => (string) $userMessage->id],
                ['field' => 'assistant_message_id', 'value' => (string) $assistantMessage->id],
                ['field' => 'provider', 'value' => (string) ($meta['provider'] ?? '')],
                ['field' => 'model', 'value' => (string) ($meta['model'] ?? '')],
                ['field' => 'vulnerability_id', 'value' => (string) ($meta['vulnerability_id'] ?? '')],
                ['field' => 'suggestions_parsed', 'value' => !empty($meta['suggestions_parsed']) ? '1' : '0'],
                ['field' => 'suggested_status', 'value' => (string) ($meta['suggested_status'] ?? '')],
                ['field' => 'suggested_business_severity', 'value' => (string) ($meta['suggested_business_severity'] ?? '')],
                ['field' => 'component_suggestions', 'value' => (string) ($meta['component_suggestions'] ?? 0)],
                ['field' => 'version_suggestions', 'value' => (string) ($meta['version_suggestions'] ?? 0)],
                ['field' => 'cross_product_hints', 'value' => (string) ($meta['cross_product_hints'] ?? 0)],
            ],
        );
    }

    public static function logReadinessReportExported(Product $product, User $actor): void
    {
        self::persist(
            type: AuditEventType::ReadinessReportExported,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $product->organization_id,
            productId: $product->id,
            details: [
                ['field' => 'product_id', 'value' => (string) $product->id],
                ['field' => 'name', 'value' => $product->name],
            ],
        );
    }

    public static function logCompliancePassportViewed(Product $product, User $actor): void
    {
        self::persist(
            type: AuditEventType::CompliancePassportViewed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $product->organization_id,
            productId: $product->id,
            details: [
                ['field' => 'product_id', 'value' => (string) $product->id],
                ['field' => 'name', 'value' => $product->name],
            ],
        );
    }

    public static function logReportingDraftUpdated(
        ProductVulnerability $vulnerability,
        User $actor,
        string $type,
    ): void {
        self::persistReportingEvent(
            AuditEventType::ReportingDraftUpdated,
            $vulnerability,
            $actor,
            $type,
        );
    }

    public static function logReportingSubmittedForApproval(
        ProductVulnerability $vulnerability,
        User $actor,
        string $type,
    ): void {
        self::persistReportingEvent(
            AuditEventType::ReportingSubmittedForApproval,
            $vulnerability,
            $actor,
            $type,
        );
    }

    public static function logReportingApproved(
        ProductVulnerability $vulnerability,
        User $actor,
        string $type,
    ): void {
        self::persistReportingEvent(
            AuditEventType::ReportingApproved,
            $vulnerability,
            $actor,
            $type,
        );
    }

    public static function logReportingRejected(
        ProductVulnerability $vulnerability,
        User $actor,
        string $type,
    ): void {
        self::persistReportingEvent(
            AuditEventType::ReportingRejected,
            $vulnerability,
            $actor,
            $type,
        );
    }

    public static function logReportingMarkedSubmitted(
        ProductVulnerability $vulnerability,
        User $actor,
        string $type,
    ): void {
        self::persistReportingEvent(
            AuditEventType::ReportingMarkedSubmitted,
            $vulnerability,
            $actor,
            $type,
        );
    }

    public static function logReportingExported(
        ProductVulnerability $vulnerability,
        User $actor,
    ): void {
        self::persistReportingEvent(
            AuditEventType::ReportingExported,
            $vulnerability,
            $actor,
        );
    }

    public static function logVcsConnectionCreated(OrganizationVcsConnection $connection, User $actor): void
    {
        self::persist(
            type: AuditEventType::VcsConnectionCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $connection->organization_id,
            details: [
                ['field' => 'connection_id', 'value' => (string) $connection->id],
                ['field' => 'provider', 'value' => $connection->provider->value],
                ['field' => 'auth_type', 'value' => $connection->auth_type->value],
                ['field' => 'label', 'value' => $connection->label],
            ],
        );
    }

    public static function logVcsConnectionUpdated(OrganizationVcsConnection $connection, User $actor): void
    {
        self::persist(
            type: AuditEventType::VcsConnectionUpdated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $connection->organization_id,
            details: [
                ['field' => 'connection_id', 'value' => (string) $connection->id],
                ['field' => 'provider', 'value' => $connection->provider->value],
                ['field' => 'auth_type', 'value' => $connection->auth_type->value],
                ['field' => 'label', 'value' => $connection->label],
                ['field' => 'sync_schedule', 'value' => $connection->sync_schedule->value],
            ],
        );
    }

    public static function logVcsConnectionDeleted(OrganizationVcsConnection $connection, User $actor): void
    {
        self::persist(
            type: AuditEventType::VcsConnectionDeleted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $connection->organization_id,
            details: [
                ['field' => 'connection_id', 'value' => (string) $connection->id],
                ['field' => 'provider', 'value' => $connection->provider->value],
                ['field' => 'auth_type', 'value' => $connection->auth_type->value],
                ['field' => 'label', 'value' => $connection->label],
            ],
        );
    }

    public static function logVcsRepositoryLinked(ProductRepository $repository, User $actor): void
    {
        self::persist(
            type: AuditEventType::VcsRepositoryLinked,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $repository->product->organization_id,
            productId: $repository->product_id,
            details: [
                ['field' => 'repository_id', 'value' => (string) $repository->id],
                ['field' => 'full_name', 'value' => $repository->full_name],
                ['field' => 'remote_url', 'value' => $repository->remote_url],
                ['field' => 'connection_id', 'value' => (string) $repository->connection_id],
            ],
        );
    }

    public static function logVcsRepositoryUnlinked(ProductRepository $repository, User $actor): void
    {
        self::persist(
            type: AuditEventType::VcsRepositoryUnlinked,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $repository->product->organization_id,
            productId: $repository->product_id,
            details: [
                ['field' => 'repository_id', 'value' => (string) $repository->id],
                ['field' => 'full_name', 'value' => $repository->full_name],
                ['field' => 'remote_url', 'value' => $repository->remote_url],
                ['field' => 'connection_id', 'value' => (string) $repository->connection_id],
            ],
        );
    }

    public static function logVcsSyncSucceeded(ProductRepository $repository, VcsSyncRun $run, ?User $actor): void
    {
        self::persist(
            type: AuditEventType::VcsSyncSucceeded,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $repository->product->organization_id,
            productId: $repository->product_id,
            details: [
                ['field' => 'repository_id', 'value' => (string) $repository->id],
                ['field' => 'sync_run_id', 'value' => (string) $run->id],
                ['field' => 'full_name', 'value' => $repository->full_name],
                ['field' => 'tags_count', 'value' => (string) ($run->summary['tags_count'] ?? 0)],
                ['field' => 'releases_count', 'value' => (string) ($run->summary['releases_count'] ?? 0)],
            ],
        );
    }

    public static function logVcsSyncFailed(
        ProductRepository $repository,
        VcsSyncRun $run,
        ?User $actor,
        string $error,
    ): void {
        self::persist(
            type: AuditEventType::VcsSyncFailed,
            success: false,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $repository->product->organization_id,
            productId: $repository->product_id,
            details: [
                ['field' => 'repository_id', 'value' => (string) $repository->id],
                ['field' => 'sync_run_id', 'value' => (string) $run->id],
                ['field' => 'full_name', 'value' => $repository->full_name],
                ['field' => 'error', 'value' => $error],
            ],
        );
    }

    public static function logVcsSuggestionAccepted(VcsImportSuggestion $suggestion, User $actor): void
    {
        $suggestion->loadMissing('product');

        self::persist(
            type: AuditEventType::VcsSuggestionAccepted,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $suggestion->product->organization_id,
            productId: $suggestion->product_id,
            details: [
                ['field' => 'suggestion_id', 'value' => (string) $suggestion->id],
                ['field' => 'kind', 'value' => $suggestion->kind->value],
                ['field' => 'external_id', 'value' => $suggestion->external_id],
                ['field' => 'accepted_entity_type', 'value' => $suggestion->accepted_entity_type],
                ['field' => 'accepted_entity_id', 'value' => (string) ($suggestion->accepted_entity_id ?? '')],
            ],
        );
    }

    public static function logVcsSuggestionDismissed(VcsImportSuggestion $suggestion, User $actor): void
    {
        $suggestion->loadMissing('product');

        self::persist(
            type: AuditEventType::VcsSuggestionDismissed,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $suggestion->product->organization_id,
            productId: $suggestion->product_id,
            details: [
                ['field' => 'suggestion_id', 'value' => (string) $suggestion->id],
                ['field' => 'kind', 'value' => $suggestion->kind->value],
                ['field' => 'external_id', 'value' => $suggestion->external_id],
            ],
        );
    }

    public static function logReportingEscalationCreated(
        ProductVulnerability $vulnerability,
        User $actor,
        int $taskId,
    ): void {
        self::persist(
            type: AuditEventType::ReportingEscalationCreated,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $vulnerability->product->organization_id,
            productId: $vulnerability->product_id,
            details: [
                ['field' => 'vulnerability_id', 'value' => (string) $vulnerability->id],
                ['field' => 'product_id', 'value' => (string) $vulnerability->product_id],
                ['field' => 'title', 'value' => $vulnerability->title],
                ['field' => 'task_id', 'value' => (string) $taskId],
            ],
        );
    }

    private static function persistReportingEvent(
        AuditEventType $type,
        ProductVulnerability $vulnerability,
        User $actor,
        ?string $reportType = null,
    ): void {
        $details = [
            ['field' => 'vulnerability_id', 'value' => (string) $vulnerability->id],
            ['field' => 'product_id', 'value' => (string) $vulnerability->product_id],
            ['field' => 'title', 'value' => $vulnerability->title],
        ];

        if ($reportType !== null) {
            $details[] = ['field' => 'report_type', 'value' => $reportType];
        }

        self::persist(
            type: $type,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
            organizationId: $vulnerability->product->organization_id,
            productId: $vulnerability->product_id,
            details: $details,
        );
    }

    /**
     * @param  list<array{field: string, value?: string|null, initial_value?: string|null, final_value?: string|null}>  $details
     */
    private static function persist(
        AuditEventType $type,
        bool $success,
        AuditEventSource $source,
        ?User $actor = null,
        ?string $email = null,
        ?string $name = null,
        ?int $organizationId = null,
        ?int $productId = null,
        array $details = [],
    ): void {
        $resolvedActor = $actor ?? (Auth::user() instanceof User ? Auth::user() : null);

        AuditLog::query()->create([
            'occurred_at' => now(),
            'event_type' => $type,
            'event_source' => $source,
            'is_success' => $success,
            'organization_id' => $organizationId,
            'product_id' => $productId,
            'user_id' => $resolvedActor?->id,
            'user_email' => $email ?? $resolvedActor?->email ?? '—',
            'user_name' => $name ?? $resolvedActor?->name ?? '—',
            'description' => json_encode(
                self::sanitizeDetails($details),
                JSON_UNESCAPED_UNICODE,
            ),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<array<string, mixed>>
     */
    private static function sanitizeDetails(array $details): array
    {
        $sensitive = array_fill_keys(self::SENSITIVE_KEYS, true);

        return array_values(array_filter(array_map(
            function (array $row) use ($sensitive): ?array {
                $field = strtolower((string) ($row['field'] ?? ''));

                if ($field !== '' && isset($sensitive[$field])) {
                    return null;
                }

                foreach (array_keys($row) as $key) {
                    if (isset($sensitive[strtolower((string) $key)])) {
                        unset($row[$key]);
                    }
                }

                return $row;
            },
            $details,
        )));
    }
}

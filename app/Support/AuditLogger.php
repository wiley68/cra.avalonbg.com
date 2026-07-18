<?php

namespace App\Support;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\Task;
use App\Models\User;
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
        'recovery_code',
        'code',
        'otp',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
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

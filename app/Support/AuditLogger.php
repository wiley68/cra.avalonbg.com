<?php

namespace App\Support;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Models\AuditLog;
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

    public static function logTaskApproved(Task $task, User $actor, ?string $comment = null): void
    {
        self::persist(
            type: AuditEventType::TaskApproved,
            success: true,
            source: self::resolveSource(),
            actor: $actor,
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
            details: [
                ['field' => 'task_id', 'value' => (string) $task->id],
                ['field' => 'product_id', 'value' => (string) $task->product_id],
                ['field' => 'title', 'value' => $task->title],
                ['field' => 'comment', 'value' => $comment],
            ],
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
        array $details = [],
    ): void {
        $resolvedActor = $actor ?? (Auth::user() instanceof User ? Auth::user() : null);

        AuditLog::query()->create([
            'occurred_at' => now(),
            'event_type' => $type,
            'event_source' => $source,
            'is_success' => $success,
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

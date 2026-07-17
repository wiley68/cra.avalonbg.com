<?php

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function createAuditLog(array $overrides = []): AuditLog
{
    return AuditLog::query()->create(array_merge([
        'occurred_at' => now(),
        'event_type' => AuditEventType::LoginSuccess,
        'event_source' => AuditEventSource::Workspace,
        'is_success' => true,
        'user_id' => null,
        'user_email' => 'audit@example.com',
        'user_name' => 'Audit User',
        'description' => json_encode([
            ['field' => 'email', 'value' => 'audit@example.com'],
        ], JSON_UNESCAPED_UNICODE),
    ], $overrides));
}

test('successful login creates audit log entry', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
        'two_factor_confirmed_at' => null,
    ]);

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $log = AuditLog::query()
        ->where(AuditLog::COLUMN_EVENT_TYPE, AuditEventType::LoginSuccess)
        ->where(AuditLog::COLUMN_USER_EMAIL, $user->email)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->is_success)->toBeTrue()
        ->and($log->event_source)->toBe(AuditEventSource::Workspace)
        ->and($log->user_id)->toBe($user->id);

    $details = json_decode((string) $log->description, true);
    expect($details)->toBeArray()
        ->and(collect($details)->pluck('field'))->not->toContain('password');
});

test('failed login creates audit log entry without password', function () {
    $user = User::factory()->create();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $log = AuditLog::query()
        ->where(AuditLog::COLUMN_EVENT_TYPE, AuditEventType::LoginFailed)
        ->where(AuditLog::COLUMN_USER_EMAIL, $user->email)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->is_success)->toBeFalse();

    $encoded = (string) $log->description;
    expect($encoded)->not->toContain('wrong-password')
        ->and($encoded)->not->toContain('"password"')
        ->and($encoded)->toContain('invalid_credentials');
});

test('prune command deletes audit logs older than configured retention', function () {
    config(['retention.audit_logs_years' => 1]);

    $old = createAuditLog(['occurred_at' => now()->subYears(2)]);
    $recent = createAuditLog(['occurred_at' => now()->subMonths(2)]);

    artisan('audit-logs:prune')->assertSuccessful();

    assertDatabaseMissing('audit_logs', ['id' => $old->id]);
    expect(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});

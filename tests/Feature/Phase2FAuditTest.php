<?php

use App\Enums\AuditEventType;
use App\Enums\BillingDocumentType;
use App\Enums\BillingStatus;
use App\Enums\SsoProvider;
use App\Enums\SubscriptionPlan;
use App\Mail\BillingDocumentMail;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Models\OrganizationSsoConnection;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Must 9 — audit coverage for plan change / payment activate / doc send / SSO connect.
 *
 * @return array{organization: Organization, owner: User, admin: User}
 */
function makePhase2FAuditActors(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Audit Org',
        'slug' => 'audit-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Free->value,
        'billing_status' => BillingStatus::Active->value,
        'billing_activated_at' => now(),
        'billing_email' => 'billing@audit.test',
    ]);

    $owner = User::factory()->create([
        'email' => 'owner@audit.test',
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'admin' => $admin,
    ];
}

test('admin plan change writes subscription_plan_changed audit without secrets', function () {
    ['organization' => $organization, 'admin' => $admin] = makePhase2FAuditActors();

    $this->actingAs($admin)
        ->put(route('admin.organizations.update', $organization), [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'billing_email' => $organization->billing_email,
            'subscription_plan' => SubscriptionPlan::Enterprise->value,
            'is_active' => true,
            'sso_enabled' => false,
            'locale' => 'en',
        ])
        ->assertRedirect(route('admin.organizations.edit', $organization));

    expect($organization->fresh()->subscription_plan)->toBe(SubscriptionPlan::Enterprise->value);

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::SubscriptionPlanChanged->value)
        ->where('organization_id', $organization->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->description)->toContain('free')
        ->and($log->description)->toContain('enterprise')
        ->and($log->description)->toContain('admin')
        ->and($log->description)->not->toContain('client_secret');
});

test('bank payment activate writes billing_activated audit', function () {
    ['organization' => $organization, 'owner' => $owner, 'admin' => $admin] = makePhase2FAuditActors();

    $organization->update([
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_status' => BillingStatus::PendingPayment->value,
        'billing_activated_at' => null,
    ]);

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.organizations.activate-billing', $organization))
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::BankPaymentRequested->value)
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::BillingActivated->value)
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();
});

test('billing document send writes billing_document_sent audit', function () {
    Storage::fake('local');
    Mail::fake();
    ['organization' => $organization, 'admin' => $admin] = makePhase2FAuditActors();

    $this->actingAs($admin)
        ->post(route('admin.organizations.billing-documents.store', $organization), [
            'type' => BillingDocumentType::Invoice->value,
            'title' => 'Audit invoice',
            'file' => UploadedFile::fake()->createWithContent(
                'audit-invoice.pdf',
                '%PDF-1.4 audit-doc',
            ),
        ])
        ->assertRedirect(route('admin.organizations.billing', $organization));

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.organizations.billing-documents.send', [$organization, $document]))
        ->assertRedirect();

    Mail::assertSent(BillingDocumentMail::class);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::BillingDocumentSent->value)
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();
});

test('sso connect writes sso_connection_created audit without client secret', function () {
    ['organization' => $organization, 'owner' => $owner] = makePhase2FAuditActors();

    $organization->update([
        'subscription_plan' => SubscriptionPlan::Enterprise->value,
    ]);

    $this->actingAs($owner)
        ->put(route('settings.sso.update'), [
            'provider' => SsoProvider::Generic->value,
            'issuer' => 'https://idp.example.com',
            'client_id' => 'client-audit',
            'client_secret' => 'must-not-appear-in-audit',
            'allowed_email_domains' => 'audit.test',
            'is_enabled' => true,
        ])
        ->assertRedirect(route('settings.sso.edit'));

    expect(OrganizationSsoConnection::query()
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::SsoConnectionCreated->value)
        ->where('organization_id', $organization->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('client-audit')
        ->and($log->description)->toContain('idp.example.com')
        ->and($log->description)->not->toContain('must-not-appear-in-audit');
});

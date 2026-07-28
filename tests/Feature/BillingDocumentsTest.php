<?php

use App\Enums\AuditEventType;
use App\Enums\BillingDocumentType;
use App\Enums\BillingStatus;
use App\Enums\SubscriptionPlan;
use App\Mail\BillingDocumentMail;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\BillingDocumentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeOrgWithBillingEmail(?string $billingStatus = null): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Docs Org',
        'slug' => 'docs-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_status' => $billingStatus ?? BillingStatus::Active->value,
        'billing_email' => 'billing@docs.test',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
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

    return [$organization, $owner];
}

function makeBillingPlatformAdmin(): User
{
    test()->seed([RolePermissionSeeder::class]);

    return User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
}

function billingPdf(string $name, string $body = '%PDF-1.4 billing-doc'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

function seedBillingDocument(
    Organization $organization,
    User $uploader,
    BillingDocumentType $type = BillingDocumentType::Invoice,
    string $title = 'Seeded invoice',
): OrganizationBillingDocument {
    return app(BillingDocumentService::class)->upload(
        $organization,
        $uploader,
        billingPdf('seeded.pdf'),
        $type,
        $title,
    );
}

test('tenant cannot upload billing documents via settings', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();

    $this->actingAs($owner)
        ->post('/settings/billing/documents', [
            'type' => BillingDocumentType::Invoice->value,
            'title' => 'July invoice',
            'file' => billingPdf('invoice-july.pdf'),
        ])
        ->assertNotFound();

    expect(OrganizationBillingDocument::query()
        ->where('organization_id', $organization->id)
        ->count())->toBe(0);
});

test('tenant can download a billing document after payment is active', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();
    $admin = makeBillingPlatformAdmin();
    $document = seedBillingDocument($organization, $admin);

    $this->actingAs($owner)
        ->get(route('settings.billing.documents.download', $document))
        ->assertOk();
});

test('tenant cannot download billing documents before payment is confirmed', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail(BillingStatus::PendingPayment->value);
    $admin = makeBillingPlatformAdmin();
    $document = seedBillingDocument($organization, $admin);

    $this->actingAs($owner)
        ->get(route('settings.billing.documents.download', $document))
        ->assertForbidden();
});

test('tenant cannot send or delete billing documents', function () {
    Storage::fake('local');
    Mail::fake();
    [$organization, $owner] = makeOrgWithBillingEmail();
    $admin = makeBillingPlatformAdmin();
    $document = seedBillingDocument($organization, $admin, BillingDocumentType::License, 'License pack');

    $this->actingAs($owner)
        ->post("/settings/billing/documents/{$document->id}/send")
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete("/settings/billing/documents/{$document->id}")
        ->assertMethodNotAllowed();

    expect($document->fresh())->not->toBeNull();
    Mail::assertNothingSent();
});

test('admin can upload and send billing documents for an organization', function () {
    Storage::fake('local');
    Mail::fake();
    [$organization] = makeOrgWithBillingEmail();
    $admin = makeBillingPlatformAdmin();

    $this->actingAs($admin)
        ->post(route('admin.organizations.billing-documents.store', $organization), [
            'type' => BillingDocumentType::Invoice->value,
            'title' => 'Admin invoice',
            'file' => billingPdf('admin-invoice.pdf'),
        ])
        ->assertRedirect(route('admin.organizations.billing', $organization));

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.organizations.billing-documents.send', [$organization, $document]))
        ->assertRedirect();

    Mail::assertSent(BillingDocumentMail::class);
    expect($document->fresh()->sent_to_email)->toBe('billing@docs.test');
    expect(AuditLog::query()->where('event_type', AuditEventType::BillingDocumentUploaded->value)->exists())
        ->toBeTrue();
});

test('admin send falls back to owner email when billing_email is empty', function () {
    Storage::fake('local');
    Mail::fake();
    [$organization, $owner] = makeOrgWithBillingEmail();
    $organization->update(['billing_email' => null]);
    $admin = makeBillingPlatformAdmin();
    $document = seedBillingDocument($organization, $admin);

    $this->actingAs($admin)
        ->post(route('admin.organizations.billing-documents.send', [$organization, $document]))
        ->assertRedirect();

    Mail::assertSent(BillingDocumentMail::class, fn(BillingDocumentMail $mail) => $mail->hasTo($owner->email));
});

test('admin can delete a billing document', function () {
    Storage::fake('local');
    [$organization] = makeOrgWithBillingEmail();
    $admin = makeBillingPlatformAdmin();
    $document = seedBillingDocument($organization, $admin);
    $path = $document->storage_path;

    $this->actingAs($admin)
        ->delete(route('admin.organizations.billing-documents.destroy', [$organization, $document]))
        ->assertRedirect();

    expect(OrganizationBillingDocument::query()->find($document->id))->toBeNull();
    expect(Storage::disk('local')->exists($path))->toBeFalse();
    expect(AuditLog::query()->where('event_type', AuditEventType::BillingDocumentDeleted->value)->exists())
        ->toBeTrue();
});

test('billing settings shows documents only when billing is active', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();
    $admin = makeBillingPlatformAdmin();
    seedBillingDocument($organization, $admin, BillingDocumentType::Invoice, 'Visible invoice');

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Billing')
            ->has('documents', 1)
            ->where('documents.0.title', 'Visible invoice')
            ->where('canManageDocuments', false));

    $organization->update(['billing_status' => BillingStatus::PendingPayment->value]);

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Billing')
            ->has('documents', 0)
            ->where('canManageDocuments', false));
});

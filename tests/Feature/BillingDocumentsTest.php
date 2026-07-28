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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeOrgWithBillingEmail(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Docs Org',
        'slug' => 'docs-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_status' => BillingStatus::Active->value,
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

test('tenant can upload invoice and license documents', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::Invoice->value,
            'title' => 'July invoice',
            'file' => billingPdf('invoice-july.pdf'),
        ])
        ->assertRedirect(route('settings.billing.edit'));

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::License->value,
            'file' => billingPdf('license.pdf', '%PDF-1.4 license'),
        ])
        ->assertRedirect();

    $documents = OrganizationBillingDocument::query()
        ->where('organization_id', $organization->id)
        ->orderBy('id')
        ->get();

    expect($documents)->toHaveCount(2)
        ->and($documents[0]->type)->toBe(BillingDocumentType::Invoice)
        ->and($documents[0]->title)->toBe('July invoice')
        ->and($documents[1]->type)->toBe(BillingDocumentType::License);

    expect(Storage::disk('local')->exists($documents[0]->storage_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($documents[1]->storage_path))->toBeTrue();

    expect(AuditLog::query()->where('event_type', AuditEventType::BillingDocumentUploaded->value)->count())
        ->toBe(2);
});

test('tenant can download a billing document', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::Invoice->value,
            'file' => billingPdf('invoice.pdf'),
        ]);

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();

    $this->actingAs($owner)
        ->get(route('settings.billing.documents.download', $document))
        ->assertOk();
});

test('tenant can send billing document to billing email', function () {
    Storage::fake('local');
    Mail::fake();
    [$organization, $owner] = makeOrgWithBillingEmail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::License->value,
            'title' => 'License pack',
            'file' => billingPdf('license.pdf'),
        ]);

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.send', $document))
        ->assertRedirect(route('settings.billing.edit'));

    Mail::assertSent(BillingDocumentMail::class, function (BillingDocumentMail $mail) use ($organization) {
        return $mail->hasTo('billing@docs.test')
            && $mail->organization->is($organization)
            && $mail->document->type === BillingDocumentType::License;
    });

    $document->refresh();
    expect($document->sent_at)->not->toBeNull()
        ->and($document->sent_to_email)->toBe('billing@docs.test')
        ->and($document->sent_by)->toBe($owner->id);

    expect(AuditLog::query()->where('event_type', AuditEventType::BillingDocumentSent->value)->exists())
        ->toBeTrue();
});

test('send falls back to owner email when billing_email is empty', function () {
    Storage::fake('local');
    Mail::fake();
    [$organization, $owner] = makeOrgWithBillingEmail();
    $organization->update(['billing_email' => null]);

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::Invoice->value,
            'file' => billingPdf('invoice.pdf'),
        ]);

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.send', $document))
        ->assertRedirect();

    Mail::assertSent(BillingDocumentMail::class, fn(BillingDocumentMail $mail) => $mail->hasTo($owner->email));
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
        ->assertRedirect(route('admin.organizations.edit', $organization));

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.organizations.billing-documents.send', [$organization, $document]))
        ->assertRedirect();

    Mail::assertSent(BillingDocumentMail::class);
    expect($document->fresh()->sent_to_email)->toBe('billing@docs.test');
});

test('tenant can delete a billing document', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::Invoice->value,
            'file' => billingPdf('invoice.pdf'),
        ]);

    /** @var OrganizationBillingDocument $document */
    $document = $organization->billingDocuments()->firstOrFail();
    $path = $document->storage_path;

    $this->actingAs($owner)
        ->delete(route('settings.billing.documents.destroy', $document))
        ->assertRedirect();

    expect(OrganizationBillingDocument::query()->find($document->id))->toBeNull();
    expect(Storage::disk('local')->exists($path))->toBeFalse();
    expect(AuditLog::query()->where('event_type', AuditEventType::BillingDocumentDeleted->value)->exists())
        ->toBeTrue();
});

test('billing settings page includes documents payload', function () {
    Storage::fake('local');
    [$organization, $owner] = makeOrgWithBillingEmail();

    $this->actingAs($owner)
        ->post(route('settings.billing.documents.store'), [
            'type' => BillingDocumentType::Invoice->value,
            'title' => 'Visible invoice',
            'file' => billingPdf('invoice.pdf'),
        ]);

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Billing')
            ->has('documents', 1)
            ->where('documents.0.title', 'Visible invoice')
            ->where('documentRecipientEmail', 'billing@docs.test')
            ->has('documentTypes', 2));
});

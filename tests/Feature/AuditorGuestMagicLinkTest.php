<?php

use App\Enums\AuditEventType;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     package: AuditorReviewPackage
 * }
 */
function makeGuestLinkFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Guest Link Org',
        'slug' => 'guest-link-org',
        'is_active' => true,
        'locale' => 'en',
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Guest Link Product',
        'slug' => 'guest-link-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'External review pack',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'notes' => 'Guest scope notes',
        'created_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'product', 'package');
}

test('owner can generate guest link for shared package', function () {
    $fixture = makeGuestLinkFixture();

    $response = $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.guest-link.generate', $fixture['package']))
        ->assertRedirect(route('auditor.packages.edit', $fixture['package']));

    $fixture['package']->refresh();

    expect($fixture['package']->guest_token_hash)->not->toBeNull()
        ->and($fixture['package']->guest_token_expires_at)->not->toBeNull()
        ->and($fixture['package']->hasActiveGuestLink())->toBeTrue();

    expect(session('fresh_guest_link_url'))->toBeString()
        ->and(session('fresh_guest_link_url'))->toContain('/auditor/guest/');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageGuestLinkGenerated->value)
        ->where('organization_id', $fixture['organization']->id)
        ->exists())->toBeTrue();

    $response->assertSessionHas('fresh_guest_link_url');
});

test('guest token opens read-only review without auth', function () {
    $fixture = makeGuestLinkFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.guest-link.generate', $fixture['package']))
        ->assertRedirect();

    $url = session('fresh_guest_link_url');
    expect($url)->toBeString();

    preg_match('#/auditor/guest/([A-Fa-f0-9]{64})$#', $url, $matches);
    expect($matches[1] ?? null)->not->toBeNull();

    $this->flushSession();

    $this->get(route('auditor.guest.show', ['token' => $matches[1]]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auditor/GuestShow')
            ->where('package.title', 'External review pack')
            ->where('guest.view_only', true)
            ->where('organization.name', 'Guest Link Org')
            ->has('report.sections')
            ->has('findings'));

    expect($fixture['package']->fresh()->guest_token_last_accessed_at)->not->toBeNull();
});

test('invalid or revoked guest token returns 404', function () {
    $fixture = makeGuestLinkFixture();

    $this->get(route('auditor.guest.show', ['token' => str_repeat('a', 64)]))
        ->assertNotFound();

    $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.guest-link.generate', $fixture['package']))
        ->assertRedirect();

    $url = session('fresh_guest_link_url');
    preg_match('#/auditor/guest/([A-Fa-f0-9]{64})$#', $url, $matches);

    $this->actingAs($fixture['owner'])
        ->delete(route('auditor.packages.guest-link.revoke', $fixture['package']))
        ->assertRedirect(route('auditor.packages.edit', $fixture['package']));

    expect($fixture['package']->fresh()->guest_token_hash)->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageGuestLinkRevoked->value)
        ->exists())->toBeTrue();

    $this->get(route('auditor.guest.show', ['token' => $matches[1]]))
        ->assertNotFound();
});

test('closing package clears guest link', function () {
    $fixture = makeGuestLinkFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.guest-link.generate', $fixture['package']))
        ->assertRedirect();

    expect($fixture['package']->fresh()->hasActiveGuestLink())->toBeTrue();

    $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.close', $fixture['package']))
        ->assertRedirect();

    $fixture['package']->refresh();

    expect($fixture['package']->status)->toBe(AuditorReviewPackageStatus::Closed)
        ->and($fixture['package']->guest_token_hash)->toBeNull()
        ->and($fixture['package']->hasActiveGuestLink())->toBeFalse();
});

test('draft package cannot get a guest link', function () {
    $fixture = makeGuestLinkFixture();
    $fixture['package']->update([
        'status' => AuditorReviewPackageStatus::Draft,
        'shared_at' => null,
    ]);

    $this->actingAs($fixture['owner'])
        ->from(route('auditor.packages.edit', $fixture['package']))
        ->post(route('auditor.packages.guest-link.generate', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHasErrors('status');
});

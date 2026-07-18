<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makePassportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Passport Org',
        'slug' => 'passport-org',
        'is_active' => true,
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
        'name' => 'Passport Product',
        'slug' => 'passport-product',
        'manufacturer' => 'Avalon',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'product_owner_user_id' => $owner->id,
        'security_contact_user_id' => $owner->id,
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
    ];
}

test('owner can view compliance passport', function () {
    ['owner' => $owner, 'product' => $product] = makePassportFixture();

    $this->actingAs($owner)
        ->get(route('products.passport.show', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/passport/Show')
            ->where('product.id', $product->id)
            ->where('product.manufacturer', 'Avalon')
            ->has('report.sections', 15));

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::CompliancePassportViewed)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('foreign org member cannot view compliance passport', function () {
    ['product' => $product] = makePassportFixture();

    $foreignOrg = Organization::query()->create([
        'name' => 'Other Passport Org',
        'slug' => 'other-passport-org',
        'is_active' => true,
    ]);

    $foreign = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $foreignOrg->users()->attach($foreign->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($foreign)
        ->get(route('products.passport.show', $product))
        ->assertNotFound();
});

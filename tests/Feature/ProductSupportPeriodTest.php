<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SupportPeriodType;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductSupportPeriod;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeSupportPeriodFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
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
        'name' => 'Payments Module',
        'slug' => 'payments-module',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
    ];
}

test('owner can create structured support period', function () {
    ['owner' => $owner, 'product' => $product] = makeSupportPeriodFixture();

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => 'released',
        'support_status' => 'supported',
    ]);

    $response = $this->actingAs($owner)->post(
        route('products.support-periods.store', $product),
        [
            'type' => SupportPeriodType::Security->value,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'basis' => 'CRA security support commitment',
            'is_extended' => false,
            'version_ids' => [$version->id],
        ],
    );

    $response->assertRedirect(route('products.support-periods.index', $product));

    $period = ProductSupportPeriod::query()->where('product_id', $product->id)->first();
    expect($period)->not->toBeNull();
    expect($period->type)->toBe(SupportPeriodType::Security);
    expect($period->versions()->pluck('product_versions.id')->all())->toContain($version->id);
});

test('support periods index is reachable', function () {
    ['owner' => $owner, 'product' => $product] = makeSupportPeriodFixture();

    $this->actingAs($owner)
        ->get(route('products.support-periods.index', $product))
        ->assertOk();
});

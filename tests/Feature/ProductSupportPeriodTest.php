<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SupportPeriodStartBasis;
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
        'release_date' => now()->subMonth()->toDateString(),
    ]);

    $response = $this->actingAs($owner)->post(
        route('products.support-periods.store', $product),
        [
            'type' => SupportPeriodType::Security->value,
            'start_basis' => SupportPeriodStartBasis::ReleaseDate->value,
            'duration_months' => 60,
            'basis' => 'CRA security support commitment',
            'is_extended' => false,
            'version_ids' => [$version->id],
        ],
    );

    $response->assertRedirect(route('products.support-periods.index', $product));

    $period = ProductSupportPeriod::query()->where('product_id', $product->id)->first();
    expect($period)->not->toBeNull();
    expect($period->type)->toBe(SupportPeriodType::Security);
    expect($period->start_basis)->toBe(SupportPeriodStartBasis::ReleaseDate);
    expect($period->duration_months)->toBe(60);
    expect($period->versions()->pluck('product_versions.id')->all())->toContain($version->id);
    expect($period->scheduleResolved())->toBeTrue();
    expect($period->isActive())->toBeTrue();
    expect($period->daysUntilEnd())->not->toBeNull();
});

test('owner can update support period version links', function () {
    ['owner' => $owner, 'product' => $product] = makeSupportPeriodFixture();

    $versionA = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => 'released',
        'support_status' => 'supported',
    ]);

    $versionB = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => 'released',
        'support_status' => 'supported',
    ]);

    $period = ProductSupportPeriod::query()->create([
        'product_id' => $product->id,
        'type' => SupportPeriodType::Commercial,
        'start_basis' => SupportPeriodStartBasis::PurchaseDate,
        'duration_months' => 12,
        'is_extended' => false,
    ]);
    $period->versions()->sync([$versionA->id]);

    $this->actingAs($owner)->put(
        route('products.support-periods.update', [$product, $period]),
        [
            'type' => SupportPeriodType::Commercial->value,
            'start_basis' => SupportPeriodStartBasis::PurchaseDate->value,
            'duration_months' => 24,
            'is_extended' => true,
            'version_ids' => [$versionB->id],
        ],
    )->assertRedirect(route('products.support-periods.index', $product));

    $period->refresh();
    expect($period->is_extended)->toBeTrue();
    expect($period->duration_months)->toBe(24);
    expect($period->versions()->pluck('product_versions.id')->all())
        ->toEqual([$versionB->id]);
    expect($period->scheduleResolved())->toBeFalse();
    expect($period->isActive())->toBeNull();
});

test('purchase date periods remain policy only without calendar status', function () {
    ['product' => $product] = makeSupportPeriodFixture();

    $period = ProductSupportPeriod::query()->create([
        'product_id' => $product->id,
        'type' => SupportPeriodType::Commercial,
        'start_basis' => SupportPeriodStartBasis::PurchaseDate,
        'duration_months' => 12,
        'is_extended' => false,
    ]);

    expect($period->scheduleResolved())->toBeFalse();
    expect($period->effectiveStartsAt())->toBeNull();
    expect($period->effectiveEndsAt())->toBeNull();
    expect($period->isActive())->toBeNull();
    expect($period->daysUntilEnd())->toBeNull();
});

test('support periods index is reachable', function () {
    ['owner' => $owner, 'product' => $product] = makeSupportPeriodFixture();

    $this->actingAs($owner)
        ->get(route('products.support-periods.index', $product))
        ->assertOk();
});

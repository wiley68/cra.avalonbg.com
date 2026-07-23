<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use App\Services\ProductReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, version: ProductVersion}
 */
function makeSdlReadinessFixture(
    ScopeStatus $scope = ScopeStatus::LikelyInScope,
    ProductVersionState $versionState = ProductVersionState::SecurityReview,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Readiness Org',
        'slug' => 'sdl-readiness-org-' . uniqid(),
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
        'name' => 'SDL Readiness Product',
        'slug' => 'sdl-readiness-product-' . uniqid(),
        'manufacturer' => 'Acme Soft',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => $scope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => $versionState,
        'support_status' => SupportStatus::Supported,
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
        'version' => $version,
    ];
}

test('in-scope release without approved sdl produces sdl_release_approval_missing gap', function () {
    $fixture = makeSdlReadinessFixture();

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'sdl');

            expect($section['status'])->toBe('fail')
                ->and($section['summary'])->toBe('missing')
                ->and($section['metrics']['awaiting_release'])->toBe(1)
                ->and($section['metrics']['approved_runs'])->toBe(0)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.sdl_release_approval_missing'
                    && $gap['section'] === 'sdl'
                    && $gap['status'] === 'fail'
                    && $gap['link'] === 'sdl',
                ))->toBeTrue();
        });
});

test('approved product-wide sdl run clears sdl_release_approval_missing gap', function () {
    $fixture = makeSdlReadinessFixture();

    SdlRun::query()->create([
        'organization_id' => $fixture['organization']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => null,
        'title' => 'Release security gate',
        'status' => SdlRunStatus::Approved,
        'current_stage' => SdlStage::ReleaseApproval,
        'approved_at' => now(),
        'approved_by' => $fixture['owner']->id,
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'sdl');

            expect($section['status'])->toBe('pass')
                ->and($section['summary'])->toBe('approved')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.sdl_release_approval_missing',
                ))->toBeFalse();
        });
});

test('version-pinned approved sdl covers matching awaiting release version', function () {
    $fixture = makeSdlReadinessFixture();

    SdlRun::query()->create([
        'organization_id' => $fixture['organization']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['version']->id,
        'title' => 'Pinned release gate',
        'status' => SdlRunStatus::Approved,
        'current_stage' => SdlStage::ReleaseApproval,
        'approved_at' => now(),
        'approved_by' => $fixture['owner']->id,
    ]);

    $report = app(ProductReadinessService::class)->build($fixture['product']);
    $section = collect($report['sections'])->firstWhere('key', 'sdl');

    expect($section['status'])->toBe('pass')
        ->and($section['summary'])->toBe('approved');
});

test('out-of-scope product marks sdl as not required even with release awaiting', function () {
    $fixture = makeSdlReadinessFixture(ScopeStatus::OutOfScope);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'sdl');

            expect($section['status'])->toBe('na')
                ->and($section['summary'])->toBe('not_required')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.sdl_release_approval_missing',
                ))->toBeFalse();
        });
});

test('in-scope product without release in progress does not raise sdl gap', function () {
    $fixture = makeSdlReadinessFixture(
        ScopeStatus::LikelyInScope,
        ProductVersionState::Draft,
    );

    $report = app(ProductReadinessService::class)->build($fixture['product']);
    $section = collect($report['sections'])->firstWhere('key', 'sdl');
    $gaps = collect($report['gaps']);

    expect($section['status'])->toBe('na')
        ->and($section['summary'])->toBe('no_release_in_progress')
        ->and($gaps->contains(
            fn($gap) => $gap['message_key'] === 'products.readiness.gaps.sdl_release_approval_missing',
        ))->toBeFalse();
});

test('product card readiness status is incomplete when sdl release approval gap exists', function () {
    $fixture = makeSdlReadinessFixture();
    $service = app(ProductReadinessService::class);

    $report = $service->build($fixture['product']);
    $statuses = $service->cardModuleStatuses($fixture['product']);

    expect(collect($report['gaps'])->contains(
        fn($gap) => $gap['message_key'] === 'products.readiness.gaps.sdl_release_approval_missing',
    ))->toBeTrue()
        ->and($statuses['readiness'])->toBe('incomplete');
});

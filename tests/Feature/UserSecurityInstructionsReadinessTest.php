<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeSecurityInstructionsReadinessFixture(
    ScopeStatus $scope = ScopeStatus::LikelyInScope,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Security Instructions Readiness Org',
        'slug' => 'security-instructions-readiness-org',
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
        'name' => 'Security Instructions Readiness Product',
        'slug' => 'security-instructions-readiness-product',
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

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
    ];
}

test('in-scope product without published instructions produces security_instructions_missing gap', function () {
    $fixture = makeSecurityInstructionsReadinessFixture();

    UserSecurityInstruction::query()->create([
        'organization_id' => $fixture['organization']->id,
        'product_id' => $fixture['product']->id,
        'title' => 'Draft only',
        'status' => UserSecurityInstructionStatus::Draft,
        'version_label' => '0.1',
        'locale' => 'en',
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'security_instructions');

            expect($section['status'])->toBe('fail')
                ->and($section['summary'])->toBe('missing')
                ->and($section['metrics']['published'])->toBe(0)
                ->and($section['metrics']['draft_or_review'])->toBe(1)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.security_instructions_missing'
                    && $gap['section'] === 'security_instructions'
                    && $gap['status'] === 'fail'
                    && $gap['link'] === 'security-instructions',
                ))->toBeTrue();
        });
});

test('published instructions clear security_instructions readiness gap', function () {
    $fixture = makeSecurityInstructionsReadinessFixture();

    UserSecurityInstruction::query()->create([
        'organization_id' => $fixture['organization']->id,
        'product_id' => $fixture['product']->id,
        'title' => 'Published instructions',
        'status' => UserSecurityInstructionStatus::Published,
        'version_label' => '1.0',
        'locale' => 'en',
        'published_at' => now(),
        'published_by' => $fixture['owner']->id,
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'security_instructions');

            expect($section['status'])->toBe('pass')
                ->and($section['summary'])->toBe('published')
                ->and($section['metrics']['published'])->toBe(1)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.security_instructions_missing',
                ))->toBeFalse();
        });
});

test('out-of-scope product marks security instructions as not required', function () {
    $fixture = makeSecurityInstructionsReadinessFixture(ScopeStatus::OutOfScope);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'security_instructions');

            expect($section['status'])->toBe('na')
                ->and($section['summary'])->toBe('not_required')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.security_instructions_missing',
                ))->toBeFalse();
        });
});

test('product card readiness status is incomplete when security instructions gap exists', function () {
    $fixture = makeSecurityInstructionsReadinessFixture();
    $service = app(\App\Services\ProductReadinessService::class);

    $report = $service->build($fixture['product']);
    $statuses = $service->cardModuleStatuses($fixture['product']);

    expect(collect($report['gaps'])->contains(
        fn($gap) => $gap['message_key'] === 'products.readiness.gaps.security_instructions_missing',
    ))->toBeTrue()
        ->and($statuses['readiness'])->toBe('incomplete');
});

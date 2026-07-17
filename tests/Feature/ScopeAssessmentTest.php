<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeAnswerTriState;
use App\Enums\ScopeMarketRole;
use App\Enums\ScopeQuestionKey;
use App\Enums\ScopeStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductScopeAssessment;
use App\Models\Role;
use App\Models\User;
use App\Services\ScopeAssessmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeScopeOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Scope Org',
        'slug' => 'scope-org',
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

    return [$organization, $owner];
}

function makeScopeOrgDeveloper(Organization $organization): User
{
    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $organization->users()->attach($developer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $developer;
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function scopeAnswers(array $overrides = []): array
{
    return array_merge([
        ScopeQuestionKey::ProductKind->value => ProductType::Software->value,
        ScopeQuestionKey::CommercialActivity->value => ScopeAnswerTriState::Yes->value,
        ScopeQuestionKey::NetworkOrDeviceLink->value => ScopeAnswerTriState::Yes->value,
        ScopeQuestionKey::OfferedStandalone->value => ScopeAnswerTriState::Yes->value,
        ScopeQuestionKey::SoldUnderOwnBrand->value => ScopeAnswerTriState::Yes->value,
        ScopeQuestionKey::RemoteProcessingRequired->value => ScopeAnswerTriState::Yes->value,
        ScopeQuestionKey::OtherSectorRegulation->value => ScopeAnswerTriState::No->value,
        ScopeQuestionKey::ComponentOfOtherProduct->value => ScopeAnswerTriState::No->value,
        ScopeQuestionKey::FreeOpenSource->value => ScopeAnswerTriState::No->value,
        ScopeQuestionKey::SubstantialModification->value => ScopeAnswerTriState::No->value,
        ScopeQuestionKey::MarketRole->value => ScopeMarketRole::Manufacturer->value,
        ScopeQuestionKey::OfferedInEu->value => ScopeAnswerTriState::Yes->value,
    ], $overrides);
}

function makeProductForScope(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Woo PB Calculator',
        'slug' => 'woo-pb-calculator',
        'product_type' => ProductType::Other,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);
}

test('scope assessment rules suggest likely in scope for commercial networked eu software', function () {
    $service = app(ScopeAssessmentService::class);

    expect($service->suggestStatus(scopeAnswers()))->toBe(ScopeStatus::LikelyInScope);
});

test('scope assessment rules suggest out of scope when not commercial', function () {
    $service = app(ScopeAssessmentService::class);

    expect($service->suggestStatus(scopeAnswers([
        ScopeQuestionKey::CommercialActivity->value => ScopeAnswerTriState::No->value,
    ])))->toBe(ScopeStatus::OutOfScope);
});

test('scope assessment rules suggest insufficient information when unsure', function () {
    $service = app(ScopeAssessmentService::class);

    expect($service->suggestStatus(scopeAnswers([
        ScopeQuestionKey::OfferedInEu->value => ScopeAnswerTriState::Unsure->value,
    ])))->toBe(ScopeStatus::InsufficientInformation);
});

test('scope assessment rules suggest potentially excluded when not offered in eu', function () {
    $service = app(ScopeAssessmentService::class);

    expect($service->suggestStatus(scopeAnswers([
        ScopeQuestionKey::OfferedInEu->value => ScopeAnswerTriState::No->value,
    ])))->toBe(ScopeStatus::PotentiallyExcluded);
});

test('scope assessment rules suggest further legal review for other sector regulation', function () {
    $service = app(ScopeAssessmentService::class);

    expect($service->suggestStatus(scopeAnswers([
        ScopeQuestionKey::OtherSectorRegulation->value => ScopeAnswerTriState::Yes->value,
    ])))->toBe(ScopeStatus::FurtherLegalReview);
});

test('owner can preview scope assessment suggestion', function () {
    [$organization, $owner] = makeScopeOrgWithOwner();

    $this->actingAs($owner)
        ->postJson(route('products.scope-assessment.preview'), [
            'answers' => scopeAnswers(),
        ])
        ->assertOk()
        ->assertJsonPath('suggested_status', ScopeStatus::LikelyInScope->value)
        ->assertJsonStructure(['suggested_status', 'rationale']);
});

test('owner can store scope assessment and sync product fields', function () {
    [$organization, $owner] = makeScopeOrgWithOwner();
    $product = makeProductForScope($organization, $owner);

    $this->actingAs($owner)
        ->post(route('products.scope-assessments.store', $product), [
            'answers' => scopeAnswers(),
            'final_status' => ScopeStatus::LikelyInScope->value,
            'rationale' => 'Manual override rationale',
        ])
        ->assertRedirect(route('products.edit', $product));

    $assessment = ProductScopeAssessment::query()->where('product_id', $product->id)->first();

    expect($assessment)->not->toBeNull();
    expect($assessment->suggested_status)->toBe(ScopeStatus::LikelyInScope);
    expect($assessment->final_status)->toBe(ScopeStatus::LikelyInScope);
    expect($assessment->rationale)->toBe('Manual override rationale');
    expect($assessment->reviewed_by)->toBe($owner->id);

    $product->refresh();

    expect($product->product_type)->toBe(ProductType::Software);
    expect($product->has_network_connectivity)->toBeTrue();
    expect($product->has_remote_data_processing)->toBeTrue();
    expect($product->scope_status)->toBe(ScopeStatus::LikelyInScope);
    expect($product->scope_rationale)->toBe('Manual override rationale');
    expect($product->scope_reviewed_by)->toBe($owner->id);
});

test('owner can create product with nested scope assessment', function () {
    [$organization, $owner] = makeScopeOrgWithOwner();

    $payload = [
        'name' => 'Nested Scope Product',
        'slug' => 'nested-scope-product',
        'product_type' => ProductType::Other->value,
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation->value,
        'classification_status' => ClassificationStatus::Unclassified->value,
        'skip_scope_wizard' => true,
        'scope_assessment' => [
            'answers' => scopeAnswers(),
            'final_status' => ScopeStatus::LikelyInScope->value,
            'rationale' => 'Created via wizard',
        ],
    ];

    $this->actingAs($owner)
        ->post(route('products.store'), $payload)
        ->assertRedirect(route('products.edit', Product::query()->first()));

    $product = Product::query()->where('slug', 'nested-scope-product')->firstOrFail();

    expect($product->organization_id)->toBe($organization->id);
    expect($product->scope_status)->toBe(ScopeStatus::LikelyInScope);
    expect($product->product_type)->toBe(ProductType::Software);
    expect(ProductScopeAssessment::query()->where('product_id', $product->id)->count())->toBe(1);
});

test('developer can view latest assessment but cannot store', function () {
    [$organization, $owner] = makeScopeOrgWithOwner();
    $developer = makeScopeOrgDeveloper($organization);
    $product = makeProductForScope($organization, $owner);

    app(ScopeAssessmentService::class)->storeAndApply(
        $product,
        scopeAnswers(),
        ScopeStatus::LikelyInScope,
        'Persisted',
        $owner,
    );

    $this->actingAs($developer)
        ->getJson(route('products.scope-assessments.latest', $product))
        ->assertOk()
        ->assertJsonPath('assessment.final_status', ScopeStatus::LikelyInScope->value);

    $this->actingAs($developer)
        ->post(route('products.scope-assessments.store', $product), [
            'answers' => scopeAnswers(),
            'final_status' => ScopeStatus::OutOfScope->value,
            'rationale' => 'Should fail',
        ])
        ->assertForbidden();
});

test('developer cannot preview scope assessment', function () {
    [$organization] = makeScopeOrgWithOwner();
    $developer = makeScopeOrgDeveloper($organization);

    $this->actingAs($developer)
        ->postJson(route('products.scope-assessment.preview'), [
            'answers' => scopeAnswers(),
        ])
        ->assertForbidden();
});

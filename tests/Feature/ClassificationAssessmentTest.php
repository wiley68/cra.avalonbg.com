<?php

use App\Enums\ClassificationQuestionKey;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeAnswerTriState;
use App\Enums\ScopeStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductClassification;
use App\Models\Role;
use App\Models\User;
use App\Services\ClassificationAssessmentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeClassificationOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Class Org',
        'slug' => 'class-org',
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

function makeClassificationOrgDeveloper(Organization $organization): User
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
function classificationAnswers(array $overrides = []): array
{
    $defaults = [];

    foreach (ClassificationQuestionKey::ordered() as $question) {
        $defaults[$question->value] = ScopeAnswerTriState::No->value;
    }

    return array_merge($defaults, $overrides);
}

function makeProductForClassification(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Woo PB Calculator',
        'slug' => 'woo-pb-calculator-class',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::Unclassified,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);
}

test('classification rules suggest general when all signals are no', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers()))->toBe(ClassificationStatus::General);
});

test('classification rules suggest important class i for identity products', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers([
        ClassificationQuestionKey::IdentityAccessSecurity->value => ScopeAnswerTriState::Yes->value,
    ])))->toBe(ClassificationStatus::ImportantClassI);
});

test('classification rules suggest important class ii for operating systems', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers([
        ClassificationQuestionKey::OperatingSystem->value => ScopeAnswerTriState::Yes->value,
    ])))->toBe(ClassificationStatus::ImportantClassIi);
});

test('classification rules suggest critical for critical infrastructure', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers([
        ClassificationQuestionKey::CriticalInfrastructure->value => ScopeAnswerTriState::Yes->value,
    ])))->toBe(ClassificationStatus::Critical);
});

test('classification rules suggest excluded when explicitly excluded', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers([
        ClassificationQuestionKey::ExplicitlyExcluded->value => ScopeAnswerTriState::Yes->value,
    ])))->toBe(ClassificationStatus::Excluded);
});

test('classification rules suggest unclassified when any answer is unsure', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers([
        ClassificationQuestionKey::NetworkSecurity->value => ScopeAnswerTriState::Unsure->value,
    ])))->toBe(ClassificationStatus::Unclassified);
});

test('classification rules suggest under review when critical and class ii conflict', function () {
    $service = app(ClassificationAssessmentService::class);

    expect($service->suggestStatus(classificationAnswers([
        ClassificationQuestionKey::CriticalInfrastructure->value => ScopeAnswerTriState::Yes->value,
        ClassificationQuestionKey::OperatingSystem->value => ScopeAnswerTriState::Yes->value,
    ])))->toBe(ClassificationStatus::UnderReview);
});

test('owner can preview classification suggestion', function () {
    [$organization, $owner] = makeClassificationOrgWithOwner();

    $this->actingAs($owner)
        ->postJson(route('products.classification.preview'), [
            'answers' => classificationAnswers([
                ClassificationQuestionKey::NetworkSecurity->value => ScopeAnswerTriState::Yes->value,
            ]),
        ])
        ->assertOk()
        ->assertJsonPath('suggested_status', ClassificationStatus::ImportantClassI->value)
        ->assertJsonStructure(['suggested_status', 'rationale']);
});

test('owner can store classification and sync product fields', function () {
    [$organization, $owner] = makeClassificationOrgWithOwner();
    $product = makeProductForClassification($organization, $owner);
    $nextReview = now()->addYear()->toDateString();

    $this->actingAs($owner)
        ->post(route('products.classifications.store', $product), [
            'answers' => classificationAnswers([
                ClassificationQuestionKey::IdentityAccessSecurity->value => ScopeAnswerTriState::Yes->value,
            ]),
            'final_status' => ClassificationStatus::ImportantClassI->value,
            'rationale' => 'Identity product Class I',
            'regulatory_content_version' => 'CRA Annex III/IV — 2024',
            'evidence_notes' => 'Internal design doc',
            'next_review_at' => $nextReview,
        ])
        ->assertRedirect(route('products.edit', $product));

    $assessment = ProductClassification::query()->where('product_id', $product->id)->first();

    expect($assessment)->not->toBeNull();
    expect($assessment->suggested_status)->toBe(ClassificationStatus::ImportantClassI);
    expect($assessment->final_status)->toBe(ClassificationStatus::ImportantClassI);
    expect($assessment->regulatory_content_version)->toBe('CRA Annex III/IV — 2024');
    expect($assessment->evidence_notes)->toBe('Internal design doc');
    expect($assessment->reviewed_by)->toBe($owner->id);
    expect($assessment->approved_by)->toBe($owner->id);
    expect($assessment->approved_at)->not->toBeNull();

    $product->refresh();

    expect($product->classification_status)->toBe(ClassificationStatus::ImportantClassI);
    expect($product->classification_rationale)->toBe('Identity product Class I');
    expect($product->classification_reviewed_by)->toBe($owner->id);
    expect($product->classification_next_review_at?->toDateString())->toBe($nextReview);
});

test('under review classification leaves approval empty', function () {
    [$organization, $owner] = makeClassificationOrgWithOwner();
    $product = makeProductForClassification($organization, $owner);

    app(ClassificationAssessmentService::class)->storeAndApply(
        $product,
        classificationAnswers([
            ClassificationQuestionKey::CriticalInfrastructure->value => ScopeAnswerTriState::Yes->value,
            ClassificationQuestionKey::OperatingSystem->value => ScopeAnswerTriState::Yes->value,
        ]),
        ClassificationStatus::UnderReview,
        'Needs legal review',
        'CRA Annex III/IV — 2024',
        null,
        null,
        $owner,
    );

    $assessment = ProductClassification::query()->where('product_id', $product->id)->firstOrFail();

    expect($assessment->approved_by)->toBeNull();
    expect($assessment->approved_at)->toBeNull();
});

test('storing classification appends history rows', function () {
    [$organization, $owner] = makeClassificationOrgWithOwner();
    $product = makeProductForClassification($organization, $owner);
    $service = app(ClassificationAssessmentService::class);

    $service->storeAndApply(
        $product,
        classificationAnswers(),
        ClassificationStatus::General,
        'First',
        'CRA Annex III/IV — 2024',
        null,
        null,
        $owner,
    );

    $service->storeAndApply(
        $product,
        classificationAnswers([
            ClassificationQuestionKey::BrowserOrRuntime->value => ScopeAnswerTriState::Yes->value,
        ]),
        ClassificationStatus::ImportantClassI,
        'Second',
        'CRA Annex III/IV — 2024',
        null,
        null,
        $owner,
    );

    expect(ProductClassification::query()->where('product_id', $product->id)->count())->toBe(2);
    expect($product->fresh()->latestClassification()?->rationale)->toBe('Second');
});

test('owner can create product with nested classification assessment', function () {
    [$organization, $owner] = makeClassificationOrgWithOwner();

    $payload = [
        'name' => 'Nested Class Product',
        'slug' => 'nested-class-product',
        'product_type' => ProductType::Software->value,
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope->value,
        'classification_status' => ClassificationStatus::Unclassified->value,
        'skip_scope_wizard' => true,
        'skip_classification_wizard' => true,
        'classification_assessment' => [
            'answers' => classificationAnswers([
                ClassificationQuestionKey::EndpointSecurity->value => ScopeAnswerTriState::Yes->value,
            ]),
            'final_status' => ClassificationStatus::ImportantClassI->value,
            'rationale' => 'Created via wizard',
            'regulatory_content_version' => 'CRA Annex III/IV — 2024',
            'evidence_notes' => null,
            'next_review_at' => now()->addMonths(6)->toDateString(),
        ],
    ];

    $this->actingAs($owner)
        ->post(route('products.store'), $payload)
        ->assertRedirect(route('products.edit', Product::query()->first()));

    $product = Product::query()->where('slug', 'nested-class-product')->firstOrFail();

    expect($product->organization_id)->toBe($organization->id);
    expect($product->classification_status)->toBe(ClassificationStatus::ImportantClassI);
    expect(ProductClassification::query()->where('product_id', $product->id)->count())->toBe(1);
});

test('developer can view latest classification but cannot store', function () {
    [$organization, $owner] = makeClassificationOrgWithOwner();
    $developer = makeClassificationOrgDeveloper($organization);
    $product = makeProductForClassification($organization, $owner);

    app(ClassificationAssessmentService::class)->storeAndApply(
        $product,
        classificationAnswers(),
        ClassificationStatus::General,
        'Persisted',
        'CRA Annex III/IV — 2024',
        null,
        null,
        $owner,
    );

    $this->actingAs($developer)
        ->getJson(route('products.classifications.latest', $product))
        ->assertOk()
        ->assertJsonPath('assessment.final_status', ClassificationStatus::General->value);

    $this->actingAs($developer)
        ->post(route('products.classifications.store', $product), [
            'answers' => classificationAnswers(),
            'final_status' => ClassificationStatus::Excluded->value,
            'rationale' => 'Should fail',
            'regulatory_content_version' => 'CRA Annex III/IV — 2024',
        ])
        ->assertForbidden();
});

test('developer cannot preview classification', function () {
    [$organization] = makeClassificationOrgWithOwner();
    $developer = makeClassificationOrgDeveloper($organization);

    $this->actingAs($developer)
        ->postJson(route('products.classification.preview'), [
            'answers' => classificationAnswers(),
        ])
        ->assertForbidden();
});

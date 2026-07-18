<?php

use App\Enums\ClassificationStatus;
use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Enums\LicensingModel;
use App\Enums\ProductRiskStatus;
use App\Enums\ProductType;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLevel;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use App\Enums\ScopeStatus;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductRiskService;
use Database\Seeders\RequirementCatalogueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeRisksOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class, RequirementCatalogueSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Risks Org',
        'slug' => 'risks-org',
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

function makeRisksOrgDeveloper(Organization $organization): User
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

function makeProductForRisks(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Payments Module Risks',
        'slug' => 'payments-module-risks',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);
}

test('risk level is derived from likelihood times impact', function () {
    expect(ProductRiskService::levelFromScores(1, 2)->value)->toBe(RiskLevel::Low->value);
    expect(ProductRiskService::levelFromScores(2, 3)->value)->toBe(RiskLevel::Medium->value);
    expect(ProductRiskService::levelFromScores(4, 3)->value)->toBe(RiskLevel::High->value);
    expect(ProductRiskService::levelFromScores(5, 5)->value)->toBe(RiskLevel::Critical->value);
});

test('owner can create risk with control and requirement links', function () {
    [$organization, $owner] = makeRisksOrgWithOwner();
    $product = makeProductForRisks($organization, $owner);
    $requirement = Requirement::query()->where('code', 'CRA-AI-04')->firstOrFail();

    $control = Control::query()->create([
        'organization_id' => $organization->id,
        'code' => 'CTL-AUTH',
        'name' => 'Authentication controls',
        'automation_level' => ControlAutomationLevel::SemiAutomated,
        'frequency' => ControlFrequency::Continuous,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('products.risks.store', $product), [
            'title' => 'Callback spoofing',
            'asset' => 'Payment callback endpoint',
            'threat' => 'External attacker',
            'weakness' => 'Missing signature verification',
            'attack_scenario' => 'Forged bank callback updates order status',
            'category' => RiskCategory::BrokenAuthentication->value,
            'likelihood' => RiskLikelihood::High->value,
            'impact' => RiskImpact::High->value,
            'treatment' => RiskTreatment::Mitigate->value,
            'treatment_plan' => 'Enforce HMAC verification',
            'status' => ProductRiskStatus::Open->value,
            'control_ids' => [$control->id],
            'requirement_ids' => [$requirement->id],
        ])
        ->assertRedirect();

    $risk = ProductRisk::query()
        ->where('product_id', $product->id)
        ->where('title', 'Callback spoofing')
        ->firstOrFail();

    expect($risk->initialRiskLevel())->toBe(RiskLevel::High);
    expect($risk->controls()->pluck('controls.id')->all())->toContain($control->id);
    expect($risk->requirements()->pluck('requirements.id')->all())->toContain($requirement->id);
});

test('developer can view risks index but cannot create', function () {
    [$organization, $owner] = makeRisksOrgWithOwner();
    $product = makeProductForRisks($organization, $owner);
    $developer = makeRisksOrgDeveloper($organization);

    $this->actingAs($developer)
        ->get(route('products.risks.index', $product))
        ->assertOk();

    $this->actingAs($developer)
        ->post(route('products.risks.store', $product), [
            'title' => 'Forbidden risk',
            'category' => RiskCategory::Injection->value,
            'likelihood' => RiskLikelihood::Low->value,
            'impact' => RiskImpact::Low->value,
            'treatment' => RiskTreatment::Accept->value,
            'status' => ProductRiskStatus::Open->value,
        ])
        ->assertForbidden();
});

test('owner can update residual risk and status', function () {
    [$organization, $owner] = makeRisksOrgWithOwner();
    $product = makeProductForRisks($organization, $owner);

    $risk = ProductRisk::query()->create([
        'product_id' => $product->id,
        'title' => 'Secrets in logs',
        'category' => RiskCategory::SecretsExposure,
        'likelihood' => RiskLikelihood::Medium,
        'impact' => RiskImpact::High,
        'treatment' => RiskTreatment::Mitigate,
        'status' => ProductRiskStatus::Open,
        'reviewed_by' => $owner->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->put(route('products.risks.update', [$product, $risk]), [
            'title' => 'Secrets in logs',
            'category' => RiskCategory::SecretsExposure->value,
            'likelihood' => RiskLikelihood::Medium->value,
            'impact' => RiskImpact::High->value,
            'residual_likelihood' => RiskLikelihood::Low->value,
            'residual_impact' => RiskImpact::Medium->value,
            'treatment' => RiskTreatment::Mitigate->value,
            'treatment_plan' => 'Mask PAN and secrets',
            'status' => ProductRiskStatus::InTreatment->value,
            'control_ids' => [],
            'requirement_ids' => [],
        ])
        ->assertRedirect();

    $risk->refresh();

    expect($risk->status)->toBe(ProductRiskStatus::InTreatment);
    expect($risk->residualRiskLevel())->toBe(RiskLevel::Medium);
});

test('internal api lists product risks', function () {
    [$organization, $owner] = makeRisksOrgWithOwner();
    $product = makeProductForRisks($organization, $owner);

    ProductRisk::query()->create([
        'product_id' => $product->id,
        'title' => 'Replay attack',
        'category' => RiskCategory::Tampering,
        'likelihood' => RiskLikelihood::Low,
        'impact' => RiskImpact::Medium,
        'treatment' => RiskTreatment::Mitigate,
        'status' => ProductRiskStatus::Open,
        'reviewed_by' => $owner->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.risks.index', $product))
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Replay attack')
        ->assertJsonPath('data.0.initial_risk', RiskLevel::Medium->value);
});

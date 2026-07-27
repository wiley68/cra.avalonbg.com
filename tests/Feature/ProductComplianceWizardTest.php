<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\ComplianceWizardService;
use App\Support\ComplianceWizardSpine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeWizardFixture(array $productOverrides = []): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Wizard Org',
        'slug' => 'wizard-org',
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

    $product = Product::query()->create(array_merge([
        'organization_id' => $organization->id,
        'name' => 'Wizard Product',
        'slug' => 'wizard-product',
        'manufacturer' => null,
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
        'product_owner_user_id' => null,
        'security_contact_user_id' => null,
    ], $productOverrides));

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
    ];
}

function makeWizardViewer(Organization $organization): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

test('owner can view compliance wizard', function () {
    ['owner' => $owner, 'product' => $product] = makeWizardFixture();

    $this->actingAs($owner)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/wizard/Show')
            ->where('product.id', $product->id)
            ->where('current_step_key', 'product')
            ->where('success', false)
            ->where('required_complete', false)
            ->has('steps', 25)
            ->has('steps.0', fn ($step) => $step
                ->where('key', 'product')
                ->where('number', 1)
                ->where('is_current', true)
                ->where('is_complete', false)
                ->etc()));
});

test('read-only viewer can view compliance wizard', function () {
    ['organization' => $organization, 'product' => $product] = makeWizardFixture();
    $viewer = makeWizardViewer($organization);

    $this->actingAs($viewer)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('products/wizard/Show')
            ->where('product.id', $product->id));
});

test('foreign org member cannot view compliance wizard', function () {
    ['product' => $product] = makeWizardFixture();

    $foreignOrg = Organization::query()->create([
        'name' => 'Other Wizard Org',
        'slug' => 'other-wizard-org',
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
        ->get(route('products.wizard.show', $product))
        ->assertNotFound();
});

test('current step advances after product identity and versions', function () {
    ['owner' => $owner, 'product' => $product] = makeWizardFixture();

    $product->update([
        'manufacturer' => 'Avalon',
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'product_owner_user_id' => $owner->id,
        'security_contact_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('current_step_key', 'versions'));

    ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
        'release_date' => now()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('current_step_key', 'support_periods')
            ->where('success', false));
});

test('success when all required spine steps are complete', function () {
    ['product' => $product] = makeWizardFixture();

    $statuses = [];
    foreach (ComplianceWizardSpine::steps() as $step) {
        $statuses[$step['key']] = 'complete';
    }

    $service = app(ComplianceWizardService::class);

    expect($service->requiredStepsComplete($statuses))->toBeTrue();
    expect($service->resolveCurrentStepKey($statuses))->toBeNull();

    $payload = $service->build($product);
    // Real product is incomplete — success flag comes from live statuses.
    expect($payload['success'])->toBeFalse();

    // Simulate required-complete payload shape for the UI contract.
    $statusesWithOptionalOpen = $statuses;
    $statusesWithOptionalOpen['auditor'] = 'empty';
    $statusesWithOptionalOpen['assistant'] = 'empty';

    expect($service->requiredStepsComplete($statusesWithOptionalOpen))->toBeTrue();
    expect($service->resolveCurrentStepKey($statusesWithOptionalOpen))->toBe('auditor');
    expect($service->resolveCurrentStepKey($statusesWithOptionalOpen, ['auditor']))->toBe('assistant');
    expect($service->resolveCurrentStepKey($statusesWithOptionalOpen, ['auditor', 'assistant']))->toBeNull();
});

test('wizard inertia payload includes href and content keys', function () {
    ['owner' => $owner, 'product' => $product] = makeWizardFixture();

    $this->actingAs($owner)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('organization.id')
            ->has('steps.3', fn ($step) => $step
                ->where('key', 'versions')
                ->where('label_key', 'products.wizard.steps.versions.label')
                ->where('content_key', 'products.wizard.steps.versions')
                ->where('required', true)
                ->has('href')
                ->has('status')
                ->has('status_reason.section')
                ->has('status_reason.summary')
                ->where('is_dismissed', false)
                ->etc())
            ->has('dismissed_optional')
            ->has('can_manage'));
});

test('owner can dismiss and restore optional wizard steps', function () {
    ['owner' => $owner, 'product' => $product] = makeWizardFixture();

    $this->actingAs($owner)
        ->post(route('products.wizard.dismiss-optional', $product), ['key' => 'auditor'])
        ->assertRedirect(route('products.wizard.show', $product));

    $product->refresh();
    expect($product->wizard_dismissed_optional)->toBe(['auditor']);

    $this->actingAs($owner)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dismissed_optional', ['auditor'])
            ->where('steps.23.is_dismissed', true)
            ->where('steps.23.key', 'auditor'));

    $this->actingAs($owner)
        ->post(route('products.wizard.restore-optional', $product), ['key' => 'auditor'])
        ->assertRedirect(route('products.wizard.show', $product));

    $product->refresh();
    expect($product->wizard_dismissed_optional)->toBe([]);
});

test('viewer cannot dismiss optional wizard steps', function () {
    ['organization' => $organization, 'product' => $product] = makeWizardFixture();
    $viewer = makeWizardViewer($organization);

    $this->actingAs($viewer)
        ->post(route('products.wizard.dismiss-optional', $product), ['key' => 'assistant'])
        ->assertForbidden();
});

test('wizard surfaces attention status beyond empty for incomplete modules', function () {
    ['owner' => $owner, 'product' => $product] = makeWizardFixture();

    $product->update([
        'manufacturer' => 'Avalon',
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'product_owner_user_id' => $owner->id,
        'security_contact_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('products.wizard.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('current_step_key', 'versions')
            ->where('steps.3.status', 'critical')
            ->where('steps.3.status_reason.summary', 'none'));
});

<?php

use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductRiskStatus;
use App\Enums\ProductType;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use App\Enums\ScopeStatus;
use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated organization owner sees action dashboard', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organizationId = $organization->id;
    $expectedMode = 'organization';

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->has('dashboard', fn(Assert $dashboard) => $dashboard
                ->where('mode', $expectedMode)
                ->has('organization', fn(Assert $org) => $org
                    ->where('id', $organizationId)
                    ->etc())
                ->has('actions')
                ->has('counts')
                ->has('recent_products')
                ->has('recent_open_tasks')
                ->has('recent_risks')
                ->has('recent_critical_vulnerabilities')
                ->has('recent_approved_sdl_runs')
                ->has('recent_pending_monitoring_sdl_runs')
                ->etc()));
});

test('organization dashboard includes latest three products with status', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft-recent',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $oldest = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Oldest Product',
        'slug' => 'oldest-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);
    $oldest->forceFill(['created_at' => now()->subDays(3)])->save();

    $middle = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Middle Product',
        'slug' => 'middle-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);
    $middle->forceFill(['created_at' => now()->subDays(2)])->save();

    $newest = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Newest Product',
        'slug' => 'newest-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);
    $newest->forceFill(['created_at' => now()->subDay()])->save();

    $hidden = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Fourth Should Hide',
        'slug' => 'fourth-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);
    $hidden->forceFill(['created_at' => now()->subDays(4)])->save();

    $expectedStatus = 'critical';

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->has('dashboard.recent_products', 3)
            ->where('dashboard.recent_products.0.id', $newest->id)
            ->where('dashboard.recent_products.0.name', $newest->name)
            ->where('dashboard.recent_products.0.status', $expectedStatus)
            ->where('dashboard.recent_products.1.id', $middle->id)
            ->where('dashboard.recent_products.2.id', $oldest->id));
});

test('platform admin without org sees platform dashboard', function () {
    test()->seed([RolePermissionSeeder::class]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
        'is_platform_admin' => true,
    ]);

    $expectedMode = 'platform';

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->has('dashboard', fn(Assert $dashboard) => $dashboard
                ->where('mode', $expectedMode)
                ->etc()));
});

test('open tasks action links to tasks index and previews up to three tasks', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Gateway',
        'slug' => 'gateway',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $user->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $user->id,
    ]);

    foreach (['Fix auth', 'Patch TLS', 'Rotate keys', 'Update docs'] as $title) {
        Task::query()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'title' => $title,
            'status' => TaskStatus::Open,
            'priority' => TaskPriority::Medium,
            'approval_status' => TaskApprovalStatus::NotRequired,
            'created_by' => $user->id,
        ]);
    }

    $expectedTitle = 'Fix auth';
    $firstTaskId = Task::query()->where('title', $expectedTitle)->value('id');
    $expectedHref = route('products.tasks.edit', [
        $product->id,
        $firstTaskId,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.open_tasks', 4)
            ->has('dashboard.recent_open_tasks', 3)
            ->where('dashboard.recent_open_tasks.0.id', $firstTaskId)
            ->where('dashboard.recent_open_tasks.0.title', $expectedTitle)
            ->where('dashboard.recent_open_tasks.0.href', $expectedHref)
            ->has('dashboard.actions')
            ->where('dashboard.actions', function ($actions) use ($product, $firstTaskId, $expectedTitle, $expectedHref): bool {
                $openTasks = collect($actions)->firstWhere('key', 'open_tasks');

                expect($openTasks)->not->toBeNull()
                    ->and($openTasks['count'])->toBe(4)
                    ->and($openTasks['href'])->toBe(route('products.tasks.index', $product))
                    ->and($openTasks['items'])->toHaveCount(3)
                    ->and($openTasks['items'][0]['title'])->toBe($expectedTitle)
                    ->and($openTasks['items'][0]['href'])->toBe($expectedHref);

                return true;
            }));
});

test('organization dashboard includes latest three risks with edit links', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft-risks',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Gateway',
        'slug' => 'gateway-risks',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $user->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $user->id,
    ]);

    foreach (['Risk A', 'Risk B', 'Risk C', 'Risk D'] as $title) {
        ProductRisk::query()->create([
            'product_id' => $product->id,
            'title' => $title,
            'category' => RiskCategory::UnauthorisedAccess,
            'likelihood' => RiskLikelihood::Medium,
            'impact' => RiskImpact::Medium,
            'treatment' => RiskTreatment::Mitigate,
            'status' => ProductRiskStatus::Open,
        ]);
    }

    $newestTitle = 'Risk D';
    $newestId = ProductRisk::query()->where('title', $newestTitle)->value('id');
    $expectedHref = route('products.risks.edit', [
        $product->id,
        $newestId,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.risks', 4)
            ->has('dashboard.recent_risks', 3)
            ->where('dashboard.recent_risks.0.id', $newestId)
            ->where('dashboard.recent_risks.0.title', $newestTitle)
            ->where('dashboard.recent_risks.0.href', $expectedHref));
});

test('organization dashboard includes latest three open critical vulnerabilities with edit links', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft-critical-vulns',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Gateway',
        'slug' => 'gateway-critical-vulns',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $user->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $user->id,
    ]);

    foreach (['Vuln A', 'Vuln B', 'Vuln C', 'Vuln D'] as $title) {
        ProductVulnerability::query()->create([
            'product_id' => $product->id,
            'title' => $title,
            'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
            'status' => VulnerabilityStatus::Triage,
            'business_severity' => VulnerabilityBusinessSeverity::Critical,
            'exploitation_status' => VulnerabilityExploitationStatus::None,
        ]);
    }

    ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'Closed critical ignored',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Closed,
        'business_severity' => VulnerabilityBusinessSeverity::Critical,
        'exploitation_status' => VulnerabilityExploitationStatus::None,
    ]);

    ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'Patched critical ignored',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Patched,
        'business_severity' => VulnerabilityBusinessSeverity::Critical,
        'exploitation_status' => VulnerabilityExploitationStatus::None,
    ]);

    ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'High severity ignored',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Triage,
        'business_severity' => VulnerabilityBusinessSeverity::High,
        'exploitation_status' => VulnerabilityExploitationStatus::None,
    ]);

    $newestTitle = 'Vuln D';
    $newestId = ProductVulnerability::query()->where('title', $newestTitle)->value('id');
    $expectedHref = route('products.vulnerabilities.edit', [
        $product->id,
        $newestId,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.critical_vulnerabilities', 4)
            ->has('dashboard.recent_critical_vulnerabilities', 3)
            ->where('dashboard.recent_critical_vulnerabilities.0.id', $newestId)
            ->where('dashboard.recent_critical_vulnerabilities.0.title', $newestTitle)
            ->where('dashboard.recent_critical_vulnerabilities.0.href', $expectedHref)
            ->where('dashboard.actions', fn($actions) => collect($actions)->contains(
                fn(array $action): bool => $action['key'] === 'critical_vulnerabilities'
                && $action['count'] === 4
                && $action['href'] === $expectedHref
                && ($action['items'][0]['href'] ?? null) === $expectedHref,
            )));
});

test('dashboard counts open and unclassified security incidents', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Incident Dash Org',
        'slug' => 'incident-dash-org',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Incident Dash Product',
        'slug' => 'incident-dash-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $user->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $user->id,
    ]);

    ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Open unclassified',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::High,
        'awareness_at' => now()->subHour(),
    ]);

    ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Investigating classified',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Medium,
        'awareness_at' => now()->subHours(2),
        'classified_at' => now()->subHour(),
    ]);

    ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Contained unclassified',
        'status' => IncidentStatus::Contained,
        'severity' => IncidentSeverity::Low,
        'awareness_at' => now()->subDay(),
    ]);

    ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Closed unclassified ignored',
        'status' => IncidentStatus::Closed,
        'severity' => IncidentSeverity::Low,
        'awareness_at' => now()->subDays(2),
        'closed_at' => now()->subDay(),
        'closed_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.open_incidents', 3)
            ->where('dashboard.counts.unclassified_incidents', 2)
            ->where('dashboard.actions', function ($actions): bool {
                $open = collect($actions)->firstWhere('key', 'open_incidents');
                $unclassified = collect($actions)->firstWhere('key', 'unclassified_incidents');

                expect($open)->not->toBeNull()
                    ->and($open['count'])->toBe(3)
                    ->and($open['href'])->toBe(route('products.index'))
                    ->and($unclassified)->not->toBeNull()
                    ->and($unclassified['count'])->toBe(2);

                return true;
            }));
});

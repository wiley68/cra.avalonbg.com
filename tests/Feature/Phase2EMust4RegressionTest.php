<?php

use App\Enums\ClassificationStatus;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncSchedule;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\ImportSuggestion;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\ProductRepository;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Support\Translations;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @return array{organization: Organization, owner: User, product: Product} */
function makePhase2EMust4Base(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Phase2E Must4 Org',
        'slug' => 'phase2e-must4-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
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
        'name' => 'Phase2E Must4 Product',
        'slug' => 'phase2e-must4-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makePhase2EMust4JiraLink(Organization $organization, Product $product): ProductIntegrationLink
{
    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_must4_token',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Hourly,
        'last_verified_at' => now(),
    ]);

    return ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA Project',
        'last_synced_at' => null,
    ]);
}

function makePhase2EMust4VcsRepository(Organization $organization, Product $product): ProductRepository
{
    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_must4_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'sync_schedule' => VcsSyncSchedule::Hourly,
        'last_verified_at' => now(),
    ]);

    return ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '77',
        'full_name' => 'acme/must4',
        'remote_url' => 'https://github.com/acme/must4',
        'default_branch' => 'main',
        'last_synced_at' => null,
    ]);
}

function makePhase2EMust4PendingSuggestion(Organization $organization, Product $product): ImportSuggestion
{
    $snyk = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'snyk_must4_token'],
        'label' => 'Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $snykLink = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $snyk->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
    ]);

    return ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $snykLink->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'must4-issue-1',
        'title' => 'Must4 finding',
        'payload' => [
            'title' => 'Must4 finding',
            'summary' => 'Package: demo@1.0.0',
            'severity' => 'medium',
        ],
        'status' => ImportSuggestionStatus::Pending,
    ]);
}

function makePhase2EMust4Vulnerability(Product $product, User $owner): ProductVulnerability
{
    return ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'Must4 vulnerability',
        'summary' => 'Demo vulnerability for stub triage',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Reported,
        'business_severity' => VulnerabilityBusinessSeverity::Medium,
        'exploitation_status' => VulnerabilityExploitationStatus::Unknown,
        'is_public' => false,
        'owner_user_id' => $owner->id,
    ]);
}

test('schedule sync commands succeed without a queue worker and leave jobs pending', function () {
    config(['queue.default' => 'database']);

    ['organization' => $organization, 'product' => $product] = makePhase2EMust4Base();
    makePhase2EMust4JiraLink($organization, $product);
    makePhase2EMust4VcsRepository($organization, $product);

    expect(DB::table('jobs')->count())->toBe(0);

    $this->artisan('integrations:sync-scheduled')
        ->expectsOutputToContain('Dispatched 1 integration sync job(s)')
        ->assertSuccessful();

    $this->artisan('vcs:sync-scheduled')
        ->expectsOutputToContain('Dispatched 1 VCS sync job(s)')
        ->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(2);
});

test('AI stub triage paths work while scheduled jobs remain unprocessed', function () {
    config([
        'queue.default' => 'database',
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.queue.enabled' => false,
    ]);

    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makePhase2EMust4Base();
    makePhase2EMust4JiraLink($organization, $product);
    makePhase2EMust4VcsRepository($organization, $product);
    $suggestion = makePhase2EMust4PendingSuggestion($organization, $product);
    $vulnerability = makePhase2EMust4Vulnerability($product, $owner);

    $this->artisan('integrations:sync-scheduled')->assertSuccessful();
    $this->artisan('vcs:sync-scheduled')->assertSuccessful();
    $pendingJobs = DB::table('jobs')->count();
    expect($pendingJobs)->toBeGreaterThan(0);

    $this->actingAs($owner)
        ->postJson(route('products.import-suggestions.ai-triage', [$product, $suggestion]))
        ->assertOk()
        ->assertJsonPath('human_review_required', true)
        ->assertJsonPath('provider', 'stub')
        ->assertJsonPath('summary_markdown', fn($value) => is_string($value) && str_contains(mb_strtolower($value), 'stub'));

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending)
        ->and(ProductVulnerability::query()->where('title', 'Must4 finding')->exists())->toBeFalse();

    $result = app(AiAssistantService::class)->triageVulnerability(
        $product,
        $owner,
        $vulnerability,
        'Must4 stub note',
    );

    expect($result['suggestions']['human_review_required'])->toBeTrue()
        ->and($vulnerability->fresh()->status)->toBe(VulnerabilityStatus::Reported);

    expect(DB::table('jobs')->count())->toBe($pendingJobs);
});

test('manual Sync now still completes with database queue and no worker', function () {
    Storage::fake('local');
    config(['queue.default' => 'database']);

    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makePhase2EMust4Base();
    $link = makePhase2EMust4JiraLink($organization, $product);

    $this->artisan('integrations:sync-scheduled')->assertSuccessful();
    $before = DB::table('jobs')->count();
    expect($before)->toBeGreaterThan(0);

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/search/jql' => Http::response([
            'issues' => [],
            'total' => 0,
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertRedirect();

    expect($link->fresh()->last_synced_at)->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe($before);
});

test('phase 2_E triage and assistant UI strings have distinct EN and BG copy', function () {
    $keys = [
        'products.integrations.suggestions.ai_triage_suggest',
        'products.integrations.suggestions.ai_triage_loading',
        'products.integrations.suggestions.ai_triage_discard',
        'products.integrations.suggestions.ai_triage_disclaimer',
        'products.integrations.suggestions.ai_triage_error',
        'products.integrations.suggestions.ai_triage_empty',
        'products.integrations.suggestions.ai_triage_suggested_severity',
        'products.vulnerabilities.ai_triage',
        'products.vulnerabilities.ai_triage_dialog_title',
        'products.vulnerabilities.ai_triage_dialog_description',
        'products.vulnerabilities.ai_triage_note_label',
        'products.vulnerabilities.ai_triage_note_placeholder',
        'products.vulnerabilities.ai_triage_submit',
        'assistant.disabled',
        'assistant.stub_provider_note',
        'assistant.provider_misconfigured',
        'assistant.provider_failed',
        'audit_logs.event_types.ai_vulnerability_triage_suggested',
        'audit_logs.event_types.ai_imported_finding_triage_suggested',
    ];

    foreach ($keys as $key) {
        $en = Translations::get($key, locale: 'en');
        $bg = Translations::get($key, locale: 'bg');

        expect($en)->not->toBe($key)
            ->and($en)->not->toBe('')
            ->and($bg)->not->toBe($key)
            ->and($bg)->not->toBe('')
            ->and($bg)->not->toBe($en);
    }
});

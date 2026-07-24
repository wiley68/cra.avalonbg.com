<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\ImportSuggestion;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeAzureDevOpsSettingsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'ADO Settings Org',
        'slug' => 'ado-settings-org-'.uniqid(),
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

    return compact('organization', 'owner');
}

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     integration: OrganizationIntegration
 * }
 */
function makeAzureDevOpsProductFixture(): array
{
    ['organization' => $organization, 'owner' => $owner] = makeAzureDevOpsSettingsFixture();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'ADO Product',
        'slug' => 'ado-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::AzureDevops,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://dev.azure.com',
            'organization' => 'acme',
            'pat' => 'ado_pat_token',
        ],
        'label' => 'Work Azure DevOps',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return compact('organization', 'owner', 'product', 'integration');
}

test('owner can connect azure devops with valid pat', function () {
    ['owner' => $owner] = makeAzureDevOpsSettingsFixture();

    Http::fake([
        'https://dev.azure.com/acme/_apis/projects*' => Http::response([
            'count' => 1,
            'value' => [['id' => 'proj-1', 'name' => 'CRA']],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.azure-devops.store'), [
            'organization' => 'acme',
            'pat' => 'ado_pat_token_valid',
            'label' => 'ADO',
        ])
        ->assertRedirect();

    $integration = OrganizationIntegration::query()->first();

    expect($integration)->not->toBeNull()
        ->and($integration->provider)->toBe(IntegrationProvider::AzureDevops)
        ->and($integration->credentials['organization'] ?? null)->toBe('acme')
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationConnected)->count())->toBe(1);
});

test('owner can link azure devops project and sync work items into task suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeAzureDevOpsProductFixture();

    Http::fake([
        'https://dev.azure.com/acme/_apis/projects/CRA*' => Http::response([
            'id' => 'proj-guid-1',
            'name' => 'CRA',
        ], 200),
        'https://dev.azure.com/acme/CRA/_apis/wit/wiql*' => Http::response([
            'workItems' => [
                ['id' => 42],
            ],
        ], 200),
        'https://dev.azure.com/acme/_apis/wit/workitems*' => Http::response([
            'value' => [
                [
                    'id' => 42,
                    'fields' => [
                        'System.Id' => 42,
                        'System.Title' => 'Document SBOM',
                        'System.Description' => '<div>Need SBOM export</div>',
                        'System.WorkItemType' => 'Task',
                        'System.State' => 'Active',
                        'Microsoft.VSTS.Common.Priority' => 2,
                        'System.ChangedDate' => '2026-07-24T10:00:00Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->put(route('products.integrations.update', [$product, 'azure_devops']), [
            'project' => 'CRA',
        ])
        ->assertRedirect();

    $link = ProductIntegrationLink::query()->first();

    expect($link)->not->toBeNull()
        ->and($link->external_project_key)->toBe('CRA')
        ->and($link->external_target_id)->toBe('proj-guid-1')
        ->and($link->integration_id)->toBe($integration->id);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'azure_devops']))
        ->assertRedirect();

    $suggestion = ImportSuggestion::query()->first();
    $run = IntegrationSyncRun::query()->first();
    $evidence = Evidence::query()->first();
    $summary = $link->fresh()->last_sync_summary;

    expect($run->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($suggestion)->not->toBeNull()
        ->and($suggestion->kind)->toBe(ImportSuggestionKind::Task)
        ->and($suggestion->external_id)->toBe('42')
        ->and($suggestion->status)->toBe(ImportSuggestionStatus::Pending)
        ->and($summary['provider'] ?? null)->toBe('azure_devops')
        ->and($summary['issues_count'] ?? null)->toBe(1)
        ->and($evidence->type)->toBe(EvidenceType::IntegrationSnapshot)
        ->and($evidence->source)->toBe('azure_devops:CRA')
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationSyncSucceeded)->count())->toBe(1);
});

test('accepting azure devops suggestion creates task with ado source note', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeAzureDevOpsProductFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => 'proj-guid-1',
        'external_label' => 'CRA',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '42',
        'title' => '42: Document SBOM',
        'payload' => [
            'title' => '42: Document SBOM',
            'summary' => 'Need SBOM export',
            'issue_key' => '42',
            'html_url' => 'https://dev.azure.com/acme/CRA/_workitems/edit/42',
            'priority' => '2',
        ],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $task = Task::query()->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->description)->toContain('Azure DevOps work item')
        ->and($task->description)->toContain('https://dev.azure.com/acme/CRA/_workitems/edit/42')
        ->and($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Accepted);
});

test('azure devops work items 403 soft-fails without failing sync', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeAzureDevOpsProductFixture();

    ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => 'proj-guid-1',
        'external_label' => 'CRA',
    ]);

    Http::fake([
        'https://dev.azure.com/acme/CRA/_apis/wit/wiql*' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'azure_devops']))
        ->assertRedirect();

    $summary = ProductIntegrationLink::query()->first()->last_sync_summary;

    expect(IntegrationSyncRun::query()->first()->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($summary['soft_fail'] ?? null)->toBeTrue()
        ->and($summary['issues_count'] ?? null)->toBe(0)
        ->and(ImportSuggestion::query()->count())->toBe(0);
});

test('product edit includes azure devops integration props', function () {
    ['owner' => $owner, 'product' => $product] = makeAzureDevOpsProductFixture();

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('azure_devops_integration.connected', true)
            ->where('azure_devops_link', null));
});

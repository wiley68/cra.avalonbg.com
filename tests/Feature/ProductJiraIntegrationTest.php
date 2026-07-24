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
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     integration: OrganizationIntegration
 * }
 */
function makeJiraProductLinkFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Jira Product Org',
        'slug' => 'jira-product-org-' . uniqid(),
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
        'name' => 'Jira Product',
        'slug' => 'jira-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_api_token',
        ],
        'label' => 'Work Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return compact('organization', 'owner', 'product', 'integration');
}

test('product edit includes jira integration props', function () {
    ['owner' => $owner, 'product' => $product] = makeJiraProductLinkFixture();

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/Edit')
            ->where('jira_link', null)
            ->where('jira_integration.connected', true)
            ->has('import_suggestions', 0));
});

test('owner can link jira project and audit is recorded', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeJiraProductLinkFixture();

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/project/CRA' => Http::response([
            'id' => '10001',
            'key' => 'CRA',
            'name' => 'CRA Project',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->put(route('products.integrations.update', [$product, 'jira']), [
            'project_key' => 'CRA',
        ])
        ->assertRedirect();

    $link = ProductIntegrationLink::query()->first();

    expect($link)->not->toBeNull()
        ->and($link->product_id)->toBe($product->id)
        ->and($link->integration_id)->toBe($integration->id)
        ->and($link->external_project_key)->toBe('CRA')
        ->and($link->external_target_id)->toBe('10001')
        ->and($link->external_label)->toBe('CRA Project')
        ->and($link->config['jql'] ?? null)->toBe('project = "CRA" ORDER BY updated DESC');

    expect(AuditLog::query()->where('event_type', AuditEventType::IntegrationLinked)->count())->toBe(1);
});

test('owner can unlink jira project', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeJiraProductLinkFixture();

    ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA Project',
        'config' => ['jql' => 'project = "CRA" ORDER BY updated DESC'],
    ]);

    $this->actingAs($owner)
        ->delete(route('products.integrations.destroy', [$product, 'jira']))
        ->assertRedirect();

    expect(ProductIntegrationLink::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationUnlinked)->count())->toBe(1);
});

test('owner can sync jira issues into pending task suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeJiraProductLinkFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA Project',
        'config' => ['jql' => 'project = "CRA" ORDER BY updated DESC'],
    ]);

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/search/jql' => Http::response([
            'issues' => [
                [
                    'id' => '20001',
                    'key' => 'CRA-1',
                    'fields' => [
                        'summary' => 'Fix auth gap',
                        'description' => 'Need MFA review',
                        'issuetype' => ['name' => 'Task'],
                        'priority' => ['name' => 'High'],
                        'status' => ['name' => 'To Do'],
                        'updated' => '2026-07-24T10:00:00.000+0000',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertRedirect();

    $suggestion = ImportSuggestion::query()->first();
    $run = IntegrationSyncRun::query()->first();
    $evidence = Evidence::query()->first();
    $summary = $link->fresh()->last_sync_summary;

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($suggestion)->not->toBeNull()
        ->and($suggestion->kind)->toBe(ImportSuggestionKind::Task)
        ->and($suggestion->status)->toBe(ImportSuggestionStatus::Pending)
        ->and($suggestion->external_id)->toBe('20001')
        ->and($suggestion->title)->toBe('CRA-1: Fix auth gap')
        ->and($suggestion->payload['html_url'] ?? null)->toBe('https://acme.atlassian.net/browse/CRA-1');

    expect($evidence)->not->toBeNull()
        ->and($evidence->type)->toBe(EvidenceType::IntegrationSnapshot)
        ->and($evidence->source)->toBe('jira:CRA')
        ->and($evidence->product_id)->toBe($product->id)
        ->and($evidence->checksum_sha256)->not->toBeEmpty()
        ->and($summary['issues_count'] ?? null)->toBe(1)
        ->and($summary['evidence_id'] ?? null)->toBe($evidence->id)
        ->and($summary['evidence_checksum_sha256'] ?? null)->toBe($evidence->checksum_sha256)
        ->and($summary['issue_refs'][0]['key'] ?? null)->toBe('CRA-1')
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationSyncSucceeded)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::EvidenceCreated)->count())->toBe(1);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->url() === 'https://acme.atlassian.net/rest/api/3/search/jql'
            && ($request['jql'] ?? null) === 'project = "CRA" ORDER BY updated DESC'
            && ($request['fields'] ?? null) === [
                'summary',
                'description',
                'issuetype',
                'priority',
                'status',
                'updated',
            ];
    });
});

test('owner can accept import suggestion as task', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeJiraProductLinkFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA Project',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20001',
        'title' => 'CRA-1: Fix auth gap',
        'payload' => [
            'title' => 'CRA-1: Fix auth gap',
            'summary' => 'Need MFA review',
            'issue_key' => 'CRA-1',
            'html_url' => 'https://acme.atlassian.net/browse/CRA-1',
            'priority' => 'High',
        ],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $task = Task::query()->first();
    $suggestion->refresh();

    expect($task)->not->toBeNull()
        ->and($task->product_id)->toBe($product->id)
        ->and($task->title)->toBe('CRA-1: Fix auth gap')
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->description)->toContain('https://acme.atlassian.net/browse/CRA-1')
        ->and($suggestion->status)->toBe(ImportSuggestionStatus::Accepted)
        ->and($suggestion->accepted_entity_id)->toBe($task->id)
        ->and(AuditLog::query()->where('event_type', AuditEventType::ImportSuggestionAccepted)->count())->toBe(1);
});

test('owner can dismiss import suggestion', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeJiraProductLinkFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA Project',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20001',
        'title' => 'CRA-1: Fix auth gap',
        'payload' => ['issue_key' => 'CRA-1'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.dismiss', [$product, $suggestion]))
        ->assertRedirect();

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Dismissed)
        ->and(Task::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::ImportSuggestionDismissed)->count())->toBe(1);
});

test('read-only user cannot link jira project', function () {
    ['organization' => $organization, 'product' => $product] = makeJiraProductLinkFixture();

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake();

    $this->actingAs($viewer)
        ->put(route('products.integrations.update', [$product, 'jira']), [
            'project_key' => 'CRA',
        ])
        ->assertForbidden();

    expect(ProductIntegrationLink::query()->count())->toBe(0);
});

test('read-only user cannot accept import suggestion', function () {
    ['organization' => $organization, 'product' => $product, 'integration' => $integration] = makeJiraProductLinkFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA Project',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20001',
        'title' => 'CRA-1: Fix auth gap',
        'payload' => ['issue_key' => 'CRA-1'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertForbidden();

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending)
        ->and(Task::query()->count())->toBe(0);
});

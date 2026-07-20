<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use App\Jobs\SyncProductRepositoryJob;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use App\Models\VcsWebhookDelivery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return array{owner: User, connection: OrganizationVcsConnection, repository: ProductRepository, secret: string}
 */
function makeWebhookFixture(string $secret = 'webhook-test-secret'): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Webhook Org',
        'slug' => 'webhook-org',
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

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_webhook_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'sync_schedule' => VcsSyncSchedule::Off,
        'webhook_secret' => $secret,
        'last_verified_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Webhook Product',
        'slug' => 'webhook-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '4242',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
    ]);

    return compact('owner', 'connection', 'repository', 'secret') + ['secret' => $secret];
}

/**
 * @return array<string, string>
 */
function githubWebhookHeaders(string $secret, string $event, string $deliveryId, string $rawBody): array
{
    return [
        'HTTP_X_GITHUB_EVENT' => $event,
        'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $rawBody, $secret),
        'CONTENT_TYPE' => 'application/json',
    ];
}

test('github release webhook dispatches sync for linked repository', function () {
    Queue::fake();

    ['connection' => $connection, 'repository' => $repository, 'secret' => $secret] = makeWebhookFixture();

    $payload = [
        'action' => 'published',
        'repository' => [
            'id' => 4242,
            'full_name' => 'acme/widget',
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.github', $connection),
        [],
        [],
        [],
        githubWebhookHeaders($secret, 'release', 'delivery-1', $raw),
        $raw,
    )->assertAccepted()
        ->assertJson([
            'status' => 'dispatched',
            'repository_id' => $repository->id,
            'dispatched' => true,
        ]);

    Queue::assertPushed(SyncProductRepositoryJob::class, fn(SyncProductRepositoryJob $job) => $job->repositoryId === $repository->id);

    expect(VcsWebhookDelivery::query()->where('delivery_id', 'delivery-1')->value('status'))->toBe('dispatched');
});

test('invalid github webhook signature is rejected', function () {
    Queue::fake();

    ['connection' => $connection, 'secret' => $secret] = makeWebhookFixture();

    $payload = ['repository' => ['id' => 4242, 'full_name' => 'acme/widget']];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $headers = githubWebhookHeaders($secret, 'release', 'delivery-bad', $raw);
    $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=deadbeef';

    $this->call(
        'POST',
        route('api.webhooks.github', $connection),
        [],
        [],
        [],
        $headers,
        $raw,
    )->assertForbidden();

    Queue::assertNothingPushed();
});

test('duplicate github delivery is acknowledged without redispatch', function () {
    Queue::fake();

    ['connection' => $connection, 'secret' => $secret] = makeWebhookFixture();

    $payload = [
        'repository' => [
            'id' => 4242,
            'full_name' => 'acme/widget',
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = githubWebhookHeaders($secret, 'release', 'delivery-dup', $raw);

    $this->call('POST', route('api.webhooks.github', $connection), [], [], [], $headers, $raw)
        ->assertAccepted();

    $this->call('POST', route('api.webhooks.github', $connection), [], [], [], $headers, $raw)
        ->assertOk()
        ->assertJson(['status' => 'duplicate', 'dispatched' => false]);

    Queue::assertPushed(SyncProductRepositoryJob::class, 1);
});

test('ping event is accepted without sync', function () {
    Queue::fake();

    ['connection' => $connection, 'secret' => $secret] = makeWebhookFixture();

    $payload = ['zen' => 'Keep it logically awesome.'];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.github', $connection),
        [],
        [],
        [],
        githubWebhookHeaders($secret, 'ping', 'delivery-ping', $raw),
        $raw,
    )->assertOk()
        ->assertJson(['status' => 'ping', 'dispatched' => false]);

    Queue::assertNothingPushed();
});

test('unlinked repository webhook is unmatched', function () {
    Queue::fake();

    ['connection' => $connection, 'secret' => $secret] = makeWebhookFixture();

    $payload = [
        'repository' => [
            'id' => 9999,
            'full_name' => 'acme/other',
        ],
    ];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.github', $connection),
        [],
        [],
        [],
        githubWebhookHeaders($secret, 'workflow_run', 'delivery-unmatched', $raw),
        $raw,
    )->assertOk()
        ->assertJson(['status' => 'unmatched', 'dispatched' => false]);

    Queue::assertNothingPushed();
});

test('owner can generate webhook secret and see it once', function () {
    ['owner' => $owner, 'connection' => $connection] = makeWebhookFixture();
    $connection->update(['webhook_secret' => null]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.webhook-secret.rotate', $connection))
        ->assertRedirect();

    expect($connection->fresh()->webhook_secret)->not->toBeNull();

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('connections.0.webhook_configured', true)
            ->has('revealed_webhook_secret')
            ->whereNot('revealed_webhook_secret', null));
});

test('webhook without configured secret returns service unavailable', function () {
    Queue::fake();

    ['connection' => $connection] = makeWebhookFixture();
    $connection->update(['webhook_secret' => null]);

    $payload = ['repository' => ['id' => 4242]];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.github', $connection),
        [],
        [],
        [],
        [
            'HTTP_X_GITHUB_EVENT' => 'release',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-nosecret',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=abc',
            'CONTENT_TYPE' => 'application/json',
        ],
        $raw,
    )->assertStatus(503);

    Queue::assertNothingPushed();
});

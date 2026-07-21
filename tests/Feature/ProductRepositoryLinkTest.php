<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, connection: OrganizationVcsConnection}
 */
function makeProductRepositoryFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Repo Org',
        'slug' => 'repo-org',
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
        'name' => 'Repo Product',
        'slug' => 'repo-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_test_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return compact('organization', 'owner', 'product', 'connection');
}

test('product edit includes repository props', function () {
    ['owner' => $owner, 'product' => $product] = makeProductRepositoryFixture();

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/Edit')
            ->where('repository', null)
            ->has('vcs_connections', 1));
});

test('owner can link github repository from owner slash repo', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    Http::fake([
        'api.github.com/repos/acme/widget' => Http::response([
            'id' => 4242,
            'full_name' => 'acme/widget',
            'html_url' => 'https://github.com/acme/widget',
            'default_branch' => 'main',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'acme/widget',
        ])
        ->assertRedirect();

    $repository = ProductRepository::query()->first();

    expect($repository)->not->toBeNull()
        ->and($repository->product_id)->toBe($product->id)
        ->and($repository->connection_id)->toBe($connection->id)
        ->and($repository->full_name)->toBe('acme/widget')
        ->and($repository->remote_url)->toBe('https://github.com/acme/widget')
        ->and($repository->default_branch)->toBe('main')
        ->and($repository->external_id)->toBe('4242')
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsRepositoryLinked)->count())->toBe(1);
});

test('owner can link github repository from url', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    Http::fake([
        'api.github.com/repos/acme/widget' => Http::response([
            'id' => 99,
            'full_name' => 'acme/widget',
            'html_url' => 'https://github.com/acme/widget',
            'default_branch' => 'develop',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'https://github.com/acme/widget.git',
        ])
        ->assertRedirect();

    expect(ProductRepository::query()->first()->full_name)->toBe('acme/widget')
        ->and(ProductRepository::query()->first()->default_branch)->toBe('develop');
});

test('invalid repository format is rejected', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    $this->actingAs($owner)
        ->from(route('products.edit', $product))
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'not-a-repo',
        ])
        ->assertRedirect(route('products.edit', $product))
        ->assertSessionHasErrors('repository');
});

test('missing github repository is rejected', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    Http::fake([
        'api.github.com/repos/acme/missing' => Http::response(['message' => 'Not Found'], 404),
    ]);

    $this->actingAs($owner)
        ->from(route('products.edit', $product))
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'acme/missing',
        ])
        ->assertRedirect(route('products.edit', $product))
        ->assertSessionHasErrors('repository');
});

test('owner can unlink repository', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '1',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
    ]);

    $this->actingAs($owner)
        ->delete(route('products.repository.destroy', $product))
        ->assertRedirect();

    expect(ProductRepository::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsRepositoryUnlinked)->count())->toBe(1);
});

test('owner can relink repository to a different github repo', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '1',
        'full_name' => 'acme/old-widget',
        'remote_url' => 'https://github.com/acme/old-widget',
        'default_branch' => 'main',
    ]);

    Http::fake([
        'api.github.com/repos/acme/new-widget' => Http::response([
            'id' => 777,
            'full_name' => 'acme/new-widget',
            'html_url' => 'https://github.com/acme/new-widget',
            'default_branch' => 'trunk',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'acme/new-widget',
        ])
        ->assertRedirect();

    expect(ProductRepository::query()->count())->toBe(1)
        ->and(ProductRepository::query()->first()->full_name)->toBe('acme/new-widget')
        ->and(ProductRepository::query()->first()->external_id)->toBe('777')
        ->and(ProductRepository::query()->first()->default_branch)->toBe('trunk')
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsRepositoryLinked)->count())->toBe(1);

    Http::assertSent(fn($request) => $request->method() === 'GET'
        && $request->url() === 'https://api.github.com/repos/acme/new-widget');
});

test('read-only user cannot link repository', function () {
    ['organization' => $organization, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
    $role = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake();

    $this->actingAs($viewer)
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'acme/widget',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
    expect(ProductRepository::query()->count())->toBe(0);
});

test('cannot link using another organizations connection', function () {
    ['owner' => $owner, 'product' => $product] = makeProductRepositoryFixture();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Repo Org',
        'slug' => 'other-repo-org',
        'is_active' => true,
    ]);

    $foreignConnection = OrganizationVcsConnection::query()->create([
        'organization_id' => $otherOrg->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_foreign',
        'label' => 'Foreign',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    Http::fake();

    $this->actingAs($owner)
        ->from(route('products.edit', $product))
        ->post(route('products.repository.store', $product), [
            'connection_id' => $foreignConnection->id,
            'repository' => 'acme/widget',
        ])
        ->assertRedirect(route('products.edit', $product))
        ->assertSessionHasErrors('connection_id');

    Http::assertNothingSent();
    expect(ProductRepository::query()->count())->toBe(0);
});

test('owner can link repository using github app installation token', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeProductRepositoryFixture();

    $connection->update([
        'auth_type' => VcsAuthType::GithubApp,
        'token' => null,
        'github_app_id' => '100',
        'github_installation_id' => '200',
        'github_private_key' => makeGithubAppPrivateKeyPem(),
    ]);

    Http::fake([
        'api.github.com/app/installations/200/access_tokens' => Http::response(['token' => 'ghs_link_token'], 201),
        'api.github.com/repos/acme/widget' => Http::response([
            'id' => 7,
            'full_name' => 'acme/widget',
            'html_url' => 'https://github.com/acme/widget',
            'default_branch' => 'main',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'acme/widget',
        ])
        ->assertRedirect();

    expect(ProductRepository::query()->first()?->full_name)->toBe('acme/widget');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && $request->url() === 'https://api.github.com/app/installations/200/access_tokens');
    Http::assertSent(fn($request) => $request->method() === 'GET'
        && $request->url() === 'https://api.github.com/repos/acme/widget'
        && $request->hasHeader('Authorization', 'Bearer ghs_link_token'));
});

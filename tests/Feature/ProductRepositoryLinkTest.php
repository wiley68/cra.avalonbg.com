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
        ->assertInertia(fn ($page) => $page
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

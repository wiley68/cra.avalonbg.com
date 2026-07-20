<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncRunStatus;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\User;
use App\Models\VcsSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('vcs connection stores encrypted token and links repository with sync run', function () {
    $organization = Organization::query()->create([
        'name' => 'VCS Org',
        'slug' => 'vcs-org',
        'is_active' => true,
    ]);

    $user = User::factory()->create();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'VCS Product',
        'slug' => 'vcs-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $plainToken = 'ghp_test_token_plaintext_value';

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => $plainToken,
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $rawToken = DB::table('organization_vcs_connections')->where('id', $connection->id)->value('token');
    expect($rawToken)->not->toBe($plainToken)
        ->and($connection->fresh()->token)->toBe($plainToken)
        ->and($connection->toArray())->not->toHaveKey('token');

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '12345',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
        'last_sync_summary' => ['tags' => 3],
    ]);

    $run = VcsSyncRun::query()->create([
        'repository_id' => $repository->id,
        'status' => VcsSyncRunStatus::Succeeded,
        'triggered_by' => $user->id,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
        'summary' => ['ci' => 'success'],
    ]);

    expect($organization->vcsConnections)->toHaveCount(1)
        ->and($product->repository?->id)->toBe($repository->id)
        ->and($repository->connection->id)->toBe($connection->id)
        ->and($repository->syncRuns)->toHaveCount(1)
        ->and($run->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and($run->triggeredByUser?->id)->toBe($user->id);
});

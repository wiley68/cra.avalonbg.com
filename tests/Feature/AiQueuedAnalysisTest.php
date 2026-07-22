<?php

use App\Enums\AiAnalysisJobStatus;
use App\Enums\AiAnalysisJobType;
use App\Enums\AiConversationContextType;
use App\Enums\AiDraftType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PatchCampaignStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Jobs\IndexAiEmbeddingsJob;
use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysisJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Organization;
use App\Models\PatchCampaign;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Services\AiQueuedAnalysisService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeQueuedAnalysisFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI Queue Org',
        'slug' => 'ai-queue-org',
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
        'name' => 'AI Queue Product',
        'slug' => 'ai-queue-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

test('queueAnalyseDocument dispatches job and leaves pending until processed', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.queue.enabled' => true,
    ]);

    Queue::fake();

    ['owner' => $owner, 'product' => $product] = makeQueuedAnalysisFixture();

    $file = UploadedFile::fake()->createWithContent(
        'policy.md',
        "# CVD\nDisclose within 30 days.\n",
    );

    $result = app(AiQueuedAnalysisService::class)->queueAnalyseDocument(
        $product,
        $owner,
        $file,
        'Focus CVD',
    );

    expect($result['analysis_job'])->not->toBeNull()
        ->and($result['analysis_job']->status)->toBe(AiAnalysisJobStatus::Pending)
        ->and($result['conversation']->context_type)->toBe(AiConversationContextType::DocumentAnalyser)
        ->and(AiMessage::query()->where('conversation_id', $result['conversation']->id)->count())->toBe(1);

    Queue::assertPushed(RunAiAnalysisJob::class, fn(RunAiAnalysisJob $job) => $job->analysisJobId === $result['analysis_job']->id);
});

test('RunAiAnalysisJob completes document analyse into conversation', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.queue.enabled' => true,
    ]);

    ['owner' => $owner, 'product' => $product] = makeQueuedAnalysisFixture();

    $file = UploadedFile::fake()->createWithContent(
        'policy.md',
        "# CVD\nDisclose within 30 days.\n",
    );

    $result = app(AiQueuedAnalysisService::class)->queueAnalyseDocument(
        $product,
        $owner,
        $file,
    );

    // With sync queue driver, job already ran; re-run process is idempotent for succeeded.
    $analysisJob = $result['analysis_job']->fresh();
    expect($analysisJob->status)->toBe(AiAnalysisJobStatus::Succeeded)
        ->and(AiMessage::query()->where('conversation_id', $result['conversation']->id)->count())->toBe(2)
        ->and(AiMessage::query()
            ->where('conversation_id', $result['conversation']->id)
            ->where('role', 'assistant')
            ->first()
            ?->metadata['suggestions_parsed'] ?? false)->toBeTrue();
});

test('HTTP draft queues analysis job when enabled', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.queue.enabled' => true,
    ]);

    Queue::fake();

    ['owner' => $owner, 'organization' => $organization, 'product' => $product] = makeQueuedAnalysisFixture();

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $version->id,
        'title' => 'Queue draft campaign',
        'status' => PatchCampaignStatus::Active,
        'created_by' => $owner->id,
        'started_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('products.assistant.draft', $product), [
            'campaign_id' => $campaign->id,
            'draft_type' => AiDraftType::CustomerNotification->value,
        ])
        ->assertRedirect();

    expect(AiAnalysisJob::query()
        ->where('type', AiAnalysisJobType::DraftGenerate)
        ->where('status', AiAnalysisJobStatus::Pending)
        ->exists())->toBeTrue();

    Queue::assertPushed(RunAiAnalysisJob::class);
});

test('conversation page exposes analysis_job payload', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.queue.enabled' => true,
    ]);

    ['owner' => $owner, 'product' => $product] = makeQueuedAnalysisFixture();

    $file = UploadedFile::fake()->createWithContent('a.txt', "hello world\n");
    $result = app(AiQueuedAnalysisService::class)->queueAnalyseDocument($product, $owner, $file);

    $this->actingAs($owner)
        ->get(route('products.assistant.conversations.show', [
            'product' => $product,
            'conversation' => $result['conversation'],
        ]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/assistant/Show')
            ->where('analysis_job.status', AiAnalysisJobStatus::Succeeded->value)
            ->where('queue_enabled', true));
});

test('ai:index-embeddings queues IndexAiEmbeddingsJob by default', function () {
    config([
        'ai.rag.enabled' => true,
        'ai.queue.enabled' => true,
        'ai.embeddings.provider' => 'stub',
    ]);

    Queue::fake();

    ['product' => $product] = makeQueuedAnalysisFixture();

    $this->artisan('ai:index-embeddings', ['product' => $product->id])
        ->assertSuccessful();

    expect(AiAnalysisJob::query()
        ->where('type', AiAnalysisJobType::RagIndex)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    Queue::assertPushed(IndexAiEmbeddingsJob::class);
});

test('queue disabled falls back to sync analyse without analysis_job row', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.queue.enabled' => false,
    ]);

    ['owner' => $owner, 'product' => $product] = makeQueuedAnalysisFixture();

    $file = UploadedFile::fake()->createWithContent('sync.txt', "sync path\n");
    $result = app(AiQueuedAnalysisService::class)->queueAnalyseDocument($product, $owner, $file);

    expect($result['analysis_job'])->toBeNull()
        ->and(AiAnalysisJob::query()->count())->toBe(0)
        ->and(AiConversation::query()->count())->toBe(1)
        ->and(AiMessage::query()->count())->toBe(2);
});

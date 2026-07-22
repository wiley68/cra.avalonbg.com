<?php

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Enums\ProductType;
use App\Enums\RequirementApplicabilityStatus;
use App\Enums\ScopeStatus;
use App\Models\AiConversation;
use App\Models\AiEmbeddingChunk;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Models\Regulation;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\CosineSimilarity;
use App\Services\Ai\StubEmbeddingProvider;
use App\Services\AiAssistantService;
use App\Services\AiEmbeddingIndexer;
use App\Services\AiRagRetriever;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeRagFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI RAG Org',
        'slug' => 'ai-rag-org',
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
        'name' => 'RAG Product',
        'slug' => 'rag-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $regulation = Regulation::query()->create([
        'code' => 'CRA-RAG',
        'title' => 'Cyber Resilience Act',
        'jurisdiction' => 'EU',
    ]);

    $requirement = Requirement::query()->create([
        'regulation_id' => $regulation->id,
        'code' => 'CRA-RAG-VULN-1',
        'article_ref' => 'Annex I',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $version = RequirementVersion::query()->create([
        'requirement_id' => $requirement->id,
        'version' => 1,
        'requirement_text' => 'Manufacturers shall handle and disclose vulnerabilities.',
        'plain_language' => 'Maintain a vulnerability disclosure process and coordinated disclosure timeline.',
        'is_current' => true,
        'published_at' => now(),
    ]);

    ProductRequirement::query()->create([
        'product_id' => $product->id,
        'requirement_id' => $requirement->id,
        'requirement_version_id' => $version->id,
        'status' => RequirementApplicabilityStatus::Implemented,
        'rationale' => 'CVD mailbox is monitored daily.',
    ]);

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::VulnerabilityDisclosure,
        'title' => 'Vulnerability disclosure policy',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => "We acknowledge vulnerability reports within 3 business days.\n\nCoordinated disclosure is preferred.",
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'CVD mailbox procedure',
        'notes' => 'Procedure for vulnerability disclosure intake and triage.',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'product');
}

test('stub embeddings are deterministic and cosine ranks similar text higher', function () {
    $provider = new StubEmbeddingProvider;

    $a = $provider->embed('vulnerability disclosure process');
    $b = $provider->embed('vulnerability disclosure process');
    $c = $provider->embed('unrelated shipping logistics invoice');

    expect($a)->toBe($b)
        ->and(CosineSimilarity::score($a, $b))->toBeGreaterThan(0.99)
        ->and(CosineSimilarity::score($a, $c))->toBeLessThan(CosineSimilarity::score($a, $b));
});

test('indexer builds embedding chunks for product sources', function () {
    config([
        'ai.rag.enabled' => true,
        'ai.embeddings.provider' => 'stub',
        'ai.embeddings.dimensions' => 64,
    ]);

    ['product' => $product] = makeRagFixture();

    $result = app(AiEmbeddingIndexer::class)->indexProduct($product);

    expect($result['chunks'])->toBeGreaterThan(0)
        ->and($result['sources'])->toBeGreaterThan(0)
        ->and(AiEmbeddingChunk::query()->count())->toBe($result['chunks']);

    expect(AiEmbeddingChunk::query()->where('source_type', 'org_policy')->whereNull('product_id')->exists())->toBeTrue()
        ->and(AiEmbeddingChunk::query()->where('source_type', 'evidence')->where('product_id', $product->id)->exists())->toBeTrue();
});

test('rag retriever returns vulnerability passages for matching query', function () {
    config([
        'ai.rag.enabled' => true,
        'ai.embeddings.provider' => 'stub',
        'ai.embeddings.dimensions' => 64,
        'ai.rag.min_score' => 0.01,
        'ai.rag.top_k' => 5,
    ]);

    ['product' => $product] = makeRagFixture();
    app(AiEmbeddingIndexer::class)->indexProduct($product);

    $retrieved = app(AiRagRetriever::class)->retrieve(
        $product,
        'How do we handle vulnerability disclosure?',
    );

    expect($retrieved['hits'])->toBeGreaterThan(0)
        ->and($retrieved['text'])->toContain('Retrieved passages')
        ->and($retrieved['text'])->toMatch('/vulnerability/i');
});

test('sendMessage grounds chat with rag hits in metadata and audit', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.rag.enabled' => true,
        'ai.embeddings.provider' => 'stub',
        'ai.embeddings.dimensions' => 64,
        'ai.rag.min_score' => 0.01,
    ]);

    ['owner' => $owner, 'product' => $product] = makeRagFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);
    $result = $service->sendMessage(
        $conversation,
        $owner,
        'Explain our vulnerability disclosure process',
    );

    expect($result['assistant_message']->metadata['rag_hits'])->toBeGreaterThan(0)
        ->and($result['assistant_message']->content)->toContain('Grounded workspace context');

    $log = AuditLog::query()
        ->where('event_type', 'ai_request_completed')
        ->where('product_id', $product->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('rag_hits')
        ->and($log->description)->not->toContain('CVD mailbox is monitored daily');
});

test('ai:index-embeddings command indexes a product', function () {
    config([
        'ai.rag.enabled' => true,
        'ai.embeddings.provider' => 'stub',
    ]);

    ['product' => $product] = makeRagFixture();

    $this->artisan('ai:index-embeddings', ['product' => $product->id])
        ->assertSuccessful();

    expect(AiEmbeddingChunk::query()->where('organization_id', $product->organization_id)->count())->toBeGreaterThan(0);
});

test('rag can be disabled without breaking chat', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => 'stub',
        'ai.rag.enabled' => false,
    ]);

    ['owner' => $owner, 'product' => $product] = makeRagFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);
    $result = $service->sendMessage($conversation, $owner, 'Hello');

    expect($result['assistant_message']->metadata['rag_hits'])->toBe(0)
        ->and(AiConversation::query()->count())->toBe(1)
        ->and(AiEmbeddingChunk::query()->count())->toBe(0);
});

<?php

use App\Enums\ClassificationStatus;
use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Enums\ControlSource;
use App\Enums\LicensingModel;
use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Enums\ProductControlStatus;
use App\Enums\ProductType;
use App\Enums\RequirementApplicabilityStatus;
use App\Enums\ScopeStatus;
use App\Models\Control;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductRequirement;
use App\Models\Regulation;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\AiAssistantService;
use App\Services\AiContextBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeAiContextFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI Context Org',
        'slug' => 'ai-context-org',
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
        'name' => 'Context Demo Product',
        'slug' => 'context-demo-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'manufacturer' => 'Avalon Demo GmbH',
        'intended_purpose' => 'Industrial gateway with remote monitoring',
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

test('AiContextBuilder summarises product requirements controls and approved policies', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeAiContextFixture();

    $regulation = Regulation::query()->create([
        'code' => 'CRA-CTX',
        'title' => 'Cyber Resilience Act',
        'jurisdiction' => 'EU',
    ]);

    $requirement = Requirement::query()->create([
        'regulation_id' => $regulation->id,
        'code' => 'CRA-AI-CTX-1',
        'article_ref' => 'Annex I',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $version = RequirementVersion::query()->create([
        'requirement_id' => $requirement->id,
        'version' => 1,
        'requirement_text' => 'Full legal text for context test',
        'plain_language' => 'Keep a vulnerability handling process',
        'is_current' => true,
        'published_at' => now(),
    ]);

    ProductRequirement::query()->create([
        'product_id' => $product->id,
        'requirement_id' => $requirement->id,
        'requirement_version_id' => $version->id,
        'status' => RequirementApplicabilityStatus::Implemented,
    ]);

    $control = Control::query()->create([
        'organization_id' => $organization->id,
        'code' => 'CTL-AI-CTX',
        'name' => 'Context scan control',
        'automation_level' => ControlAutomationLevel::Manual,
        'frequency' => ControlFrequency::AdHoc,
        'is_active' => true,
        'source' => ControlSource::Custom,
    ]);

    ProductControl::query()->create([
        'product_id' => $product->id,
        'control_id' => $control->id,
        'status' => ProductControlStatus::InPlace,
    ]);

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::VulnerabilityDisclosure,
        'title' => 'CVD Policy Context',
        'status' => PolicyStatus::Approved,
        'version_label' => '2.1',
        'body' => 'Should not appear in full in the AI context summary.',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $context = app(AiContextBuilder::class)->forProduct($product);

    expect($context)->toContain('## Product')
        ->and($context)->toContain('Context Demo Product')
        ->and($context)->toContain('Avalon Demo GmbH')
        ->and($context)->toContain('## Requirements')
        ->and($context)->toContain('CRA-AI-CTX-1')
        ->and($context)->toContain('implemented')
        ->and($context)->toContain('Keep a vulnerability handling process')
        ->and($context)->toContain('## Controls')
        ->and($context)->toContain('CTL-AI-CTX')
        ->and($context)->toContain('Context scan control')
        ->and($context)->toContain('## Approved policies')
        ->and($context)->toContain('CVD Policy Context')
        ->and($context)->toContain('v2.1')
        ->and($context)->not->toContain('Should not appear in full');
});

test('sendMessage auto-grounds replies with product context', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'product' => $product] = makeAiContextFixture();

    OrgPolicy::query()->create([
        'organization_id' => $product->organization_id,
        'policy_type' => PolicyType::Support,
        'title' => 'Support Policy Grounded',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => 'Support body',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);
    $result = $service->sendMessage($conversation, $owner, 'Summarise our posture');

    expect($result['assistant_message']->content)->toContain('Context Demo Product')
        ->and($result['assistant_message']->content)->toContain('Support Policy Grounded')
        ->and($result['assistant_message']->content)->toContain('Grounded workspace context')
        ->and($result['assistant_message']->metadata)->toMatchArray([
                'provider' => 'stub',
                'has_context' => true,
            ])
        ->and($result['assistant_message']->metadata['context_chars'])->toBeGreaterThan(0);
});

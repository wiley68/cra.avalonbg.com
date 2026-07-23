<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, instruction: UserSecurityInstruction}
 */
function makeUsiExportFixture(bool $published = false): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI Export Org',
        'slug' => 'usi-export-org-' . uniqid(),
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
        'name' => 'USI Export Product',
        'slug' => 'usi-export-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => true,
            'locale' => 'en',
        ])
        ->assertRedirect();

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    if ($published) {
        $instruction->sections()->update(['is_applicable' => false, 'body' => '']);
        $instruction->sections()
            ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
            ->update([
                'is_applicable' => true,
                'body' => "## Secure installation\n\nInstall with **least privilege**.",
            ]);

        test()->actingAs($owner)
            ->post(route('products.security-instructions.publish', [$product, $instruction]))
            ->assertRedirect();

        $instruction->refresh();
    }

    return compact('organization', 'owner', 'product', 'instruction');
}

function makeUsiExportViewer(Organization $organization): User
{
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

    return $viewer;
}

test('owner can export draft instructions as html and pdf with audit', function () {
    ['owner' => $owner, 'product' => $product, 'instruction' => $instruction] = makeUsiExportFixture();

    $html = $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'html',
        ]))
        ->assertOk();

    expect($html->headers->get('content-type'))->toContain('text/html');
    expect($html->headers->get('content-disposition'))->toContain('.html');
    expect($html->getContent())->toContain($instruction->title);
    expect($html->getContent())->toContain('Secure installation');

    $pdf = $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'pdf',
        ]))
        ->assertOk();

    expect($pdf->headers->get('content-type'))->toContain('application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionExported->value)
        ->where('product_id', $product->id)
        ->count())->toBe(2);
});

test('viewer can export published instructions only', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product, 'instruction' => $instruction] = makeUsiExportFixture(true);
    $viewer = makeUsiExportViewer($organization);

    expect($instruction->status)->toBe(UserSecurityInstructionStatus::Published);

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'html',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'pdf',
        ]))
        ->assertOk();

    // Draft on same product
    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Draft only',
            'version_label' => '0.1',
            'locale' => 'en',
        ]);

    $draft = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'Draft only')
        ->firstOrFail();

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $draft,
            'format' => 'html',
        ]))
        ->assertForbidden();
});

test('invalid export format is rejected', function () {
    ['owner' => $owner, 'product' => $product, 'instruction' => $instruction] = makeUsiExportFixture();

    $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'docx',
        ]))
        ->assertNotFound();
});

test('owner can export readme markdown and release zip package', function () {
    ['owner' => $owner, 'product' => $product, 'instruction' => $instruction] = makeUsiExportFixture();

    $readme = $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'readme',
        ]))
        ->assertOk();

    expect($readme->headers->get('content-type'))->toContain('text/markdown');
    expect($readme->headers->get('content-disposition'))->toContain('.md');
    expect($readme->getContent())->toContain('# ' . $instruction->title);
    expect($readme->getContent())->toContain('## Secure installation');
    expect($readme->getContent())->toContain('Secure installation');

    $release = $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'release',
        ]))
        ->assertOk();

    expect($release->headers->get('content-type'))->toContain('application/zip');
    expect($release->headers->get('content-disposition'))->toContain('.zip');

    $zipPath = $release->baseResponse->getFile()->getPathname();
    $zip = new \ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    expect($zip->locateName('README.md'))->not->toBeFalse();
    expect($zip->locateName('security-instructions.html'))->not->toBeFalse();
    expect($zip->locateName('security-instructions.pdf'))->not->toBeFalse();

    $md = $zip->getFromName('README.md');
    expect($md)->toContain('# ' . $instruction->title);
    expect($zip->getFromName('security-instructions.pdf'))->toStartWith('%PDF');
    $zip->close();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionExported->value)
        ->where('product_id', $product->id)
        ->count())->toBeGreaterThanOrEqual(2);
});

test('owner can export customer-specific installation guide with deployment meta', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product, 'instruction' => $instruction] = makeUsiExportFixture();

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Acme Hospitals',
        'external_ref' => 'ACME-42',
        'primary_contact' => 'sec@acme.example',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => null,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
        'update_channel' => 'stable',
        'custom_modifications' => false,
        'end_of_support_exception' => false,
        'notes' => 'VPN-only management plane',
    ]);

    $response = $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'html',
            'customer_id' => $customer->id,
            'deployment_id' => $deployment->id,
        ]))
        ->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('Installation guide for Acme Hospitals')
        ->and($html)->toContain('Acme Hospitals')
        ->and($html)->toContain('ACME-42')
        ->and($html)->toContain('sec@acme.example')
        ->and($html)->toContain('production')
        ->and($html)->toContain('VPN-only management plane')
        ->and($response->headers->get('content-disposition'))->toContain('for-acme-hospitals');

    $readme = $this->actingAs($owner)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'readme',
            'customer_id' => $customer->id,
        ]))
        ->assertOk()
        ->getContent();

    expect($readme)->toContain('Installation guide for Acme Hospitals')
        ->and($readme)->toContain('Acme Hospitals');

    $audit = AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionExported->value)
        ->where('product_id', $product->id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
    $details = collect(json_decode((string) $audit->description, true) ?: [])->keyBy('field');
    expect($details->get('customer_id')['value'] ?? null)->toBe((string) $customer->id)
        ->and($details->get('deployment_id')['value'] ?? null)->toBeNull();
});

test('customer guide rejects deployment from another customer', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product, 'instruction' => $instruction] = makeUsiExportFixture();

    $customerA = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Customer A',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $customerB = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Customer B',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $deploymentB = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customerB->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Staging,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $this->actingAs($owner)
        ->from(route('products.security-instructions.edit', [$product, $instruction]))
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $instruction,
            'format' => 'html',
            'customer_id' => $customerA->id,
            'deployment_id' => $deploymentB->id,
        ]))
        ->assertSessionHasErrors('deployment_id');
});

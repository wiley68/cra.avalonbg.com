<?php

use App\Enums\AuditEventType;
use App\Enums\CustomerCriticality;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeCustomersOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Customers Org',
        'slug' => 'customers-org',
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

    return [
        'organization' => $organization,
        'owner' => $owner,
    ];
}

function makeCustomersOrgViewer(Organization $organization): User
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

test('owner can view customers index', function () {
    ['owner' => $owner] = makeCustomersOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('customers/Index')
            ->where('canManage', true));
});

test('owner can create customer and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner] = makeCustomersOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('customers.store'), [
            'name' => 'Acme Bank',
            'external_ref' => 'CRM-42',
            'primary_contact' => 'ops@acme.example',
            'criticality' => CustomerCriticality::High->value,
            'notes' => 'Priority account',
            'is_active' => true,
        ])
        ->assertRedirect();

    $customer = Customer::query()->first();

    expect($customer)->not->toBeNull()
        ->and($customer->organization_id)->toBe($organization->id)
        ->and($customer->name)->toBe('Acme Bank')
        ->and($customer->criticality)->toBe(CustomerCriticality::High)
        ->and(AuditLog::query()->where('event_type', AuditEventType::CustomerCreated)->count())->toBe(1);
});

test('owner can update and delete customer with audit', function () {
    ['organization' => $organization, 'owner' => $owner] = makeCustomersOrgWithOwner();

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Old Name',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->put(route('customers.update', $customer), [
            'name' => 'New Name',
            'external_ref' => null,
            'primary_contact' => null,
            'criticality' => CustomerCriticality::Medium->value,
            'notes' => null,
            'is_active' => false,
        ])
        ->assertRedirect(route('customers.edit', $customer));

    expect($customer->fresh()->name)->toBe('New Name')
        ->and($customer->fresh()->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('event_type', AuditEventType::CustomerUpdated)->count())->toBe(1);

    $this->actingAs($owner)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    expect(Customer::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::CustomerDeleted)->count())->toBe(1);
});

test('viewer can list customers but cannot create', function () {
    ['organization' => $organization] = makeCustomersOrgWithOwner();
    $viewer = makeCustomersOrgViewer($organization);

    Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Visible Customer',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $this->actingAs($viewer)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->getJson(route('internal.customers.index'))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->post(route('customers.store'), [
            'name' => 'Forbidden',
            'criticality' => CustomerCriticality::Low->value,
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('internal api lists org customers with search and sort', function () {
    ['organization' => $organization, 'owner' => $owner] = makeCustomersOrgWithOwner();

    Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Zulu Corp',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);
    Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Alpha Corp',
        'primary_contact' => 'alpha@example.com',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.customers.index', [
            'sort_by' => 'name',
            'sort_desc' => '0',
            'search' => 'Alpha',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Alpha Corp');
});

test('cannot manage customer from another organization', function () {
    ['owner' => $owner] = makeCustomersOrgWithOwner();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Customers Org',
        'slug' => 'other-customers-org',
        'is_active' => true,
    ]);

    $foreign = Customer::query()->create([
        'organization_id' => $otherOrg->id,
        'name' => 'Foreign',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('customers.edit', $foreign))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('customers.destroy', $foreign))
        ->assertNotFound();
});

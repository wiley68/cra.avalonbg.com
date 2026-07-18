<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\UsersXlsxExportService;
use App\Support\EncryptedSevenZipArchive;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, member: User}
 */
function makeUsersExportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Export Org',
        'slug' => 'export-org',
        'is_active' => true,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $readOnlyRole = Role::query()->where('slug', 'read_only')->firstOrFail();

    $owner = User::factory()->create([
        'email' => 'owner-export@test',
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
        'is_platform_admin' => false,
    ]);

    $member = User::factory()->create([
        'email' => 'member-export@test',
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => true,
        'password' => 'SecretPassword123!',
        'two_factor_secret' => encrypt('secret-value'),
        'remember_token' => 'remember-me-token',
        'is_platform_admin' => false,
    ]);

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($member->id, [
        'role_id' => $readOnlyRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'member' => $member,
    ];
}

test('users export rows include org members without sensitive fields', function () {
    ['organization' => $organization, 'member' => $member] = makeUsersExportFixture();

    $rows = app(UsersXlsxExportService::class)->buildRows($organization);
    $flat = collect($rows)->flatten()->implode('|');

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toHaveCount(6)
        ->and($flat)->toContain('member-export@test')
        ->and($flat)->toContain('owner-export@test')
        ->and($flat)->not->toContain('SecretPassword123!')
        ->and($flat)->not->toContain('remember-me-token')
        ->and($flat)->not->toContain('secret-value');

    $emails = app(UsersXlsxExportService::class)
        ->exportableUsers($organization)
        ->pluck('email');

    expect($emails)->toContain($member->email);
});

test('user without organization cannot export users', function () {
    test()->seed([RolePermissionSeeder::class]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
        'is_platform_admin' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('users.export'), [
            'password' => 'ExportPass1!',
            'password_confirmation' => 'ExportPass1!',
        ])
        ->assertForbidden();
});

test('owner can export users when 7z is available', function () {
    $archive = app(EncryptedSevenZipArchive::class);
    if (! $archive->isAvailable()) {
        $this->markTestSkipped('7z не е наличен на сървъра.');
    }

    ['owner' => $owner] = makeUsersExportFixture();

    $response = $this->actingAs($owner)
        ->postJson(route('users.export'), [
            'password' => 'ExportPass1!',
            'password_confirmation' => 'ExportPass1!',
        ])
        ->assertOk()
        ->assertDownload();

    expect($response->headers->get('content-disposition'))
        ->toMatch('/users_export-org_.*\.7z/');
});

test('export validates password confirmation', function () {
    ['owner' => $owner] = makeUsersExportFixture();

    $this->actingAs($owner)
        ->postJson(route('users.export'), [
            'password' => 'ExportPass1!',
            'password_confirmation' => 'MismatchPass1!',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password_confirmation']);
});

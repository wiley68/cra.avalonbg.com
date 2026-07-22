<?php

use App\Enums\AuditEventType;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Jobs\SendAuditorReviewPackageSharedNotificationJob;
use App\Mail\AuditorReviewPackageSharedNotification;
use App\Models\AuditLog;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     auditor: User,
 *     auditorWithoutEmail: User,
 *     product: Product,
 *     package: AuditorReviewPackage
 * }
 */
function makeAuditorShareNotificationFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Share Notify Org',
        'slug' => 'share-notify-org',
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $auditor = User::factory()->create([
        'email' => 'auditor@example.com',
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $auditorWithoutEmail = User::factory()->create([
        'email' => '',
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $auditorRole = Role::query()->where('slug', 'auditor')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($auditor->id, [
        'role_id' => $auditorRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($auditorWithoutEmail->id, [
        'role_id' => $auditorRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Share Notify Product',
        'slug' => 'share-notify-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Q3 CRA review',
        'status' => AuditorReviewPackageStatus::Draft,
        'notes' => 'Focus on Annex I',
        'created_by' => $owner->id,
    ]);

    return compact(
        'organization',
        'owner',
        'auditor',
        'auditorWithoutEmail',
        'product',
        'package',
    );
}

test('sharing a package queues auditor email notifications', function () {
    Queue::fake();

    $fixture = makeAuditorShareNotificationFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.share', $fixture['package']))
        ->assertRedirect(route('auditor.packages.edit', $fixture['package']));

    expect($fixture['package']->fresh()->status)->toBe(AuditorReviewPackageStatus::Shared);

    Queue::assertPushed(SendAuditorReviewPackageSharedNotificationJob::class, 1);
    Queue::assertPushed(
        SendAuditorReviewPackageSharedNotificationJob::class,
        fn(SendAuditorReviewPackageSharedNotificationJob $job) => $job->packageId === $fixture['package']->id
        && $job->auditorUserId === $fixture['auditor']->id
        && $job->actorUserId === $fixture['owner']->id,
    );

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageShared->value)
        ->where('organization_id', $fixture['organization']->id)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageNotificationsQueued->value)
        ->where('organization_id', $fixture['organization']->id)
        ->exists())->toBeTrue();
});

test('share notification job sends mailable to auditor', function () {
    Mail::fake();

    $fixture = makeAuditorShareNotificationFixture();
    $fixture['package']->update([
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
    ]);

    $job = new SendAuditorReviewPackageSharedNotificationJob(
        $fixture['package']->id,
        $fixture['auditor']->id,
        $fixture['owner']->id,
    );

    $job->handle();

    Mail::assertSent(
        AuditorReviewPackageSharedNotification::class,
        fn(AuditorReviewPackageSharedNotification $mail) => $mail->hasTo($fixture['auditor']->email)
        && $mail->package->is($fixture['package'])
        && $mail->product->is($fixture['product']),
    );
});

test('share skips email queue when auditor notifications are disabled', function () {
    Queue::fake();
    config(['auditor_notifications.enabled' => false]);

    $fixture = makeAuditorShareNotificationFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('auditor.packages.share', $fixture['package']))
        ->assertRedirect(route('auditor.packages.edit', $fixture['package']));

    expect($fixture['package']->fresh()->status)->toBe(AuditorReviewPackageStatus::Shared);

    Queue::assertNothingPushed();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageNotificationsQueued->value)
        ->exists())->toBeFalse();
});

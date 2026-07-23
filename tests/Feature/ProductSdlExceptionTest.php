<?php

use App\Enums\AuditEventType;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\SdlException;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @param  mixed  $entries
 * @return array<string, mixed>|null
 */
function findSdlStageEntryProp(mixed $entries, string $stage): ?array
{
    if (!is_iterable($entries)) {
        return null;
    }

    foreach ($entries as $entry) {
        if (is_array($entry) && ($entry['stage'] ?? null) === $stage) {
            return $entry;
        }
    }

    return null;
}

test('exception status requires owner expiry and notes', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Exception validation run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::CodeReview,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::CodeReview->value,
        ]), [
            'status' => SdlStageStatus::Exception->value,
            'notes' => '',
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors(['notes', 'exception_owner_user_id', 'exception_expires_at']);
});

test('recording an exception creates follow-up task and audit event', function () {
    $case = seedSdlExceptionRecordingCase();
    submitSdlExceptionStageUpdate($case);
    [$exceptionId, $taskId] = assertSdlExceptionAndTaskCreated($case);
    assertSdlExceptionShownOnEditPage($case, $exceptionId, $taskId);
});

test('clearing exception removes record cancels open task and audits', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Clear exception run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::SecurityTest,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::SecurityTest->value,
        ]), [
            'status' => SdlStageStatus::Exception->value,
            'notes' => 'Temporary waiver.',
            'exception_owner_user_id' => $owner->id,
            'exception_expires_at' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect();

    $entry = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::SecurityTest->value)
        ->firstOrFail();

    $exceptionId = SdlException::query()
        ->where('sdl_stage_entry_id', $entry->id)
        ->value('id');

    expect($exceptionId)->not->toBeNull();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::SecurityTest->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Resolved — tests passed.',
        ])
        ->assertRedirect();

    expect(SdlException::query()->whereKey($exceptionId)->exists())->toBeFalse();
    expect(
        Task::query()
            ->where('subject_type', SdlException::class)
            ->where('subject_id', $exceptionId)
            ->value('status'),
    )->toBe(TaskStatus::Cancelled);
    expect(
        AuditLog::query()
            ->where('event_type', AuditEventType::SdlExceptionCleared->value)
            ->where('product_id', $product->id)
            ->exists(),
    )->toBeTrue();
});

test('viewer cannot record an sdl exception', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    $viewer = makeSdlOrgReadOnly($organization);
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer blocked run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::Design,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($viewer)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Design->value,
        ]), [
            'status' => SdlStageStatus::Exception->value,
            'notes' => 'Should not work.',
            'exception_owner_user_id' => $owner->id,
            'exception_expires_at' => now()->addDays(7)->toDateString(),
        ])
        ->assertForbidden();
});

test('expired exception is flagged in detail payload', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Expired exception run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::Development,
    ]);
    $run->ensureStageEntries();

    Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00'));

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Development->value,
        ]), [
            'status' => SdlStageStatus::Exception->value,
            'notes' => 'Short waiver.',
            'exception_owner_user_id' => $owner->id,
            'exception_expires_at' => '2026-07-10',
        ])
        ->assertRedirect();

    Carbon::setTestNow(Carbon::parse('2026-07-12 12:00:00'));

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->where(
                'run.stage_entries',
                fn(mixed $entries): bool => (findSdlStageEntryProp(
                    $entries,
                    SdlStage::Development->value,
                )['exception']['is_expired'] ?? false) === true,
            );
        });

    Carbon::setTestNow();
});

function sdlExceptionPayloadMatches(
    mixed $entries,
    string $stage,
    int $exceptionId,
    int $ownerUserId,
    string $expiresAt,
    int $taskId,
): bool {
    $stageEntry = findSdlStageEntryProp($entries, $stage);

    return $stageEntry !== null
        && ($stageEntry['exception']['id'] ?? null) === $exceptionId
        && ($stageEntry['exception']['owner_user_id'] ?? null) === $ownerUserId
        && ($stageEntry['exception']['expires_at'] ?? null) === $expiresAt
        && ($stageEntry['exception']['task']['id'] ?? null) === $taskId;
}

/**
 * @return array{owner_id: int, product_id: int, run_id: int, expires_at: string, stage: string}
 */
function seedSdlExceptionRecordingCase(): array
{
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Exception run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::DependencyScan,
    ]);
    $run->ensureStageEntries();

    return [
        'owner_id' => $owner->id,
        'product_id' => $product->id,
        'run_id' => $run->id,
        'expires_at' => now()->addDays(14)->toDateString(),
        'stage' => SdlStage::DependencyScan->value,
    ];
}

/**
 * @param  array{owner_id: int, product_id: int, run_id: int, expires_at: string, stage: string}  $case
 */
function submitSdlExceptionStageUpdate(array $case): void
{
    test()->actingAs(\App\Models\User::query()->findOrFail($case['owner_id']))
        ->put(route('products.sdl.stages.update', [
            'product' => $case['product_id'],
            'sdlRun' => $case['run_id'],
            'stage' => $case['stage'],
        ]), [
            'status' => SdlStageStatus::Exception->value,
            'notes' => 'Scanner false positive accepted for this release.',
            'exception_owner_user_id' => $case['owner_id'],
            'exception_expires_at' => $case['expires_at'],
        ])
        ->assertRedirect(route('products.sdl.edit', [
            $case['product_id'],
            $case['run_id'],
        ]));
}

/**
 * @param  array{owner_id: int, product_id: int, run_id: int, expires_at: string, stage: string}  $case
 * @return array{0: int, 1: int}
 */
function assertSdlExceptionAndTaskCreated(array $case): array
{
    $entryId = SdlStageEntry::query()
        ->where('sdl_run_id', $case['run_id'])
        ->where('stage', $case['stage'])
        ->value('id');

    expect($entryId)->not->toBeNull();

    $exception = SdlException::query()
        ->where('sdl_stage_entry_id', $entryId)
        ->firstOrFail();

    $task = Task::query()
        ->where('subject_type', SdlException::class)
        ->where('subject_id', $exception->id)
        ->firstOrFail();

    expect($exception->owner_user_id)->toBe($case['owner_id']);
    expect($exception->expires_at->toDateString())->toBe($case['expires_at']);
    expect($task->status)->toBe(TaskStatus::Open);
    expect($task->assignee_user_id)->toBe($case['owner_id']);
    expect(optional($task->due_at)?->toDateString())->toBe($case['expires_at']);
    expect(
        SdlStageEntry::query()->whereKey($entryId)->firstOrFail()->status,
    )->toBe(SdlStageStatus::Exception);
    expect(
        AuditLog::query()
            ->where('event_type', AuditEventType::SdlExceptionRecorded->value)
            ->where('product_id', $case['product_id'])
            ->exists(),
    )->toBeTrue();

    return [$exception->id, $task->id];
}

/**
 * @param  array{owner_id: int, product_id: int, run_id: int, expires_at: string, stage: string}  $case
 */
function assertSdlExceptionShownOnEditPage(array $case, int $exceptionId, int $taskId): void
{
    $stage = $case['stage'];
    $ownerUserId = $case['owner_id'];
    $expiresAt = $case['expires_at'];

    test()->actingAs(\App\Models\User::query()->findOrFail($case['owner_id']))
        ->get(route('products.sdl.edit', [$case['product_id'], $case['run_id']]))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($stage, $exceptionId, $ownerUserId, $expiresAt, $taskId, ): void {
            $page->component('products/sdl/Edit')
                ->where(
                    'run.stage_entries',
                    fn(mixed $entries): bool => sdlExceptionPayloadMatches(
                        $entries,
                        $stage,
                        $exceptionId,
                        $ownerUserId,
                        $expiresAt,
                        $taskId,
                    ),
                );
        });
}

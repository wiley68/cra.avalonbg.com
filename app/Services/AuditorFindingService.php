<?php

namespace App\Services;

use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use App\Enums\AuditorReviewPackageStatus;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Validation\ValidationException;

class AuditorFindingService
{
    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     severity: string
     * }  $attributes
     */
    public function create(
        AuditorReviewPackage $package,
        array $attributes,
        User $actor,
    ): AuditorFinding {
        $this->assertPackageAcceptsFindings($package);

        $finding = AuditorFinding::query()->create([
            'package_id' => $package->id,
            'title' => trim($attributes['title']),
            'body' => trim($attributes['body']),
            'severity' => AuditorFindingSeverity::from($attributes['severity']),
            'status' => AuditorFindingStatus::Open,
            'created_by' => $actor->id,
            'remediated_at' => null,
        ]);

        $finding->load(['creator:id,name', 'package']);
        AuditLogger::logAuditorFindingCreated($finding, $actor);

        return $finding;
    }

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     severity: string
     * }  $attributes
     */
    public function updateContent(
        AuditorFinding $finding,
        array $attributes,
        User $actor,
    ): AuditorFinding {
        $package = $finding->package;
        if ($package === null || $package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_editable'),
            ]);
        }

        $finding->update([
            'title' => trim($attributes['title']),
            'body' => trim($attributes['body']),
            'severity' => AuditorFindingSeverity::from($attributes['severity']),
        ]);

        $finding = $finding->fresh(['creator:id,name', 'package']);
        AuditLogger::logAuditorFindingUpdated($finding, $actor);

        return $finding;
    }

    public function updateStatus(
        AuditorFinding $finding,
        AuditorFindingStatus $status,
        User $actor,
    ): AuditorFinding {
        $package = $finding->package;
        if ($package === null || $package->status === AuditorReviewPackageStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_or_closed_status'),
            ]);
        }

        $finding->update([
            'status' => $status,
            'remediated_at' => $status === AuditorFindingStatus::Remediated
                ? ($finding->remediated_at ?? now())
                : null,
        ]);

        $finding = $finding->fresh(['creator:id,name', 'package']);
        AuditLogger::logAuditorFindingStatusUpdated($finding, $actor);

        return $finding;
    }

    public function delete(AuditorFinding $finding, User $actor): void
    {
        $package = $finding->package;
        if ($package === null || $package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_deletable'),
            ]);
        }

        if ($finding->status !== AuditorFindingStatus::Open) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_open_deletable'),
            ]);
        }

        AuditLogger::logAuditorFindingDeleted($finding, $actor);
        $finding->delete();
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     body: string,
     *     severity: string,
     *     status: string,
     *     created_by: int,
     *     created_by_name: string|null,
     *     remediated_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function payload(AuditorFinding $finding): array
    {
        return [
            'id' => $finding->id,
            'title' => $finding->title,
            'body' => $finding->body,
            'severity' => $finding->severity->value,
            'status' => $finding->status->value,
            'created_by' => $finding->created_by,
            'created_by_name' => $finding->creator?->name,
            'remediated_at' => $finding->remediated_at?->toIso8601String(),
            'created_at' => $finding->created_at?->toIso8601String(),
            'updated_at' => $finding->updated_at?->toIso8601String(),
        ];
    }

    private function assertPackageAcceptsFindings(AuditorReviewPackage $package): void
    {
        if ($package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_creatable'),
            ]);
        }
    }
}

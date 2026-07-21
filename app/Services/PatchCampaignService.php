<?php

namespace App\Services;

use App\Enums\PatchCampaignStatus;
use App\Enums\PatchCampaignTargetStatus;
use App\Jobs\SendPatchCampaignCustomerNotificationJob;
use App\Models\PatchCampaign;
use App\Models\PatchCampaignTarget;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CustomerContactEmail;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatchCampaignService
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     target_version_id: int,
     *     target_version_number: string|null,
     *     product_vulnerability_id: int|null,
     *     vulnerability_title: string|null,
     *     targets_count: int,
     *     started_at: string|null,
     *     completed_at: string|null,
     *     created_at: string|null
     * }>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'id',
        string $sortOrder = 'desc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = PatchCampaign::query()
            ->where('patch_campaigns.product_id', $product->id)
            ->leftJoin(
                'product_versions',
                'product_versions.id',
                '=',
                'patch_campaigns.target_version_id',
            )
            ->leftJoin(
                'product_vulnerabilities',
                'product_vulnerabilities.id',
                '=',
                'patch_campaigns.product_vulnerability_id',
            )
            ->withCount('targets')
            ->select('patch_campaigns.*')
            ->with(['targetVersion', 'productVulnerability']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('patch_campaigns.title', 'like', "%{$search}%")
                    ->orWhere('patch_campaigns.status', 'like', "%{$search}%")
                    ->orWhere('patch_campaigns.notes', 'like', "%{$search}%")
                    ->orWhere('product_versions.version_number', 'like', "%{$search}%")
                    ->orWhere('product_vulnerabilities.title', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('patch_campaigns.id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'title' => 'patch_campaigns.title',
            'status' => 'patch_campaigns.status',
            'target_version_number' => 'product_versions.version_number',
            'started_at' => 'patch_campaigns.started_at',
            'targets_count' => 'targets_count',
            default => 'patch_campaigns.id',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(PatchCampaign $campaign) => $this->listItemPayload($campaign));
    }

    /**
     * @param  array{
     *     title: string,
     *     target_version_id: int,
     *     product_vulnerability_id?: int|null,
     *     notes?: string|null,
     *     activate?: bool
     * }  $attributes
     */
    public function create(Product $product, array $attributes, User $actor): PatchCampaign
    {
        $this->assertTargetVersionBelongsToProduct($product, (int) $attributes['target_version_id']);
        $this->assertVulnerabilityBelongsToProduct(
            $product,
            isset($attributes['product_vulnerability_id'])
            ? (int) $attributes['product_vulnerability_id']
            : null,
        );

        $campaign = PatchCampaign::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'target_version_id' => $attributes['target_version_id'],
            'product_vulnerability_id' => $attributes['product_vulnerability_id'] ?? null,
            'title' => $attributes['title'],
            'status' => PatchCampaignStatus::Draft,
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        AuditLogger::logPatchCampaignCreated(
            $campaign->loadMissing(['targetVersion', 'product']),
            $actor,
        );

        if (!empty($attributes['activate'])) {
            return $this->activate($campaign, $actor);
        }

        return $campaign;
    }

    /**
     * @param  array{
     *     title: string,
     *     target_version_id: int,
     *     product_vulnerability_id?: int|null,
     *     notes?: string|null
     * }  $attributes
     */
    public function update(PatchCampaign $campaign, array $attributes, User $actor): PatchCampaign
    {
        $this->assertDraft($campaign);

        $product = $campaign->product()->firstOrFail();
        $this->assertTargetVersionBelongsToProduct($product, (int) $attributes['target_version_id']);
        $this->assertVulnerabilityBelongsToProduct(
            $product,
            isset($attributes['product_vulnerability_id'])
            ? (int) $attributes['product_vulnerability_id']
            : null,
        );

        $campaign->update([
            'title' => $attributes['title'],
            'target_version_id' => $attributes['target_version_id'],
            'product_vulnerability_id' => $attributes['product_vulnerability_id'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);

        $fresh = $campaign->fresh(['targetVersion', 'product']);
        AuditLogger::logPatchCampaignUpdated($fresh, $actor);

        return $fresh;
    }

    public function activate(PatchCampaign $campaign, User $actor): PatchCampaign
    {
        $this->assertDraft($campaign);

        return DB::transaction(function () use ($campaign, $actor): PatchCampaign {
            $deploymentIds = $this->matchingDeploymentIds(
                $campaign->product_id,
                $campaign->target_version_id,
            );

            $now = now();

            foreach ($deploymentIds as $deploymentId) {
                PatchCampaignTarget::query()->create([
                    'campaign_id' => $campaign->id,
                    'deployment_id' => $deploymentId,
                    'status' => PatchCampaignTargetStatus::Pending,
                ]);
            }

            $campaign->update([
                'status' => PatchCampaignStatus::Active,
                'started_at' => $now,
            ]);

            $fresh = $campaign->fresh(['targetVersion', 'product', 'targets']);
            AuditLogger::logPatchCampaignActivated($fresh, $actor, count($deploymentIds));
            $this->maybeCompleteCampaign($fresh, $actor);

            return $fresh->fresh(['targetVersion', 'product', 'targets']);
        });
    }

    public function delete(PatchCampaign $campaign, User $actor): void
    {
        $this->assertDraft($campaign);

        $campaign->loadMissing(['targetVersion', 'product']);
        AuditLogger::logPatchCampaignDeleted($campaign, $actor);
        $campaign->delete();
    }

    /**
     * @param  array{
     *     status: PatchCampaignTargetStatus,
     *     notification_note?: string|null
     * }  $attributes
     */
    public function updateTargetStatus(
        PatchCampaign $campaign,
        PatchCampaignTarget $target,
        array $attributes,
        User $actor,
    ): PatchCampaignTarget {
        $this->assertActive($campaign);

        if ($target->campaign_id !== $campaign->id) {
            abort(404);
        }

        $status = $attributes['status'];
        $previousStatus = $target->status->value;
        $now = now();

        $updates = [
            'status' => $status,
        ];

        if (array_key_exists('notification_note', $attributes)) {
            $updates['notification_note'] = $attributes['notification_note'];
        }

        if ($status === PatchCampaignTargetStatus::Notified) {
            $updates['notified_at'] = $target->notified_at ?? $now;
        }

        if ($status === PatchCampaignTargetStatus::Acknowledged) {
            $updates['acknowledged_at'] = $target->acknowledged_at ?? $now;
        }

        if (
            $status === PatchCampaignTargetStatus::Updated
            || $status === PatchCampaignTargetStatus::Excepted
        ) {
            $updates['confirmed_at'] = $now;
        }

        return DB::transaction(function () use ($campaign, $target, $updates, $status, $actor, $previousStatus, $now, ): PatchCampaignTarget {
            $target->update($updates);

            if ($status === PatchCampaignTargetStatus::Updated) {
                $deployment = ProductDeployment::query()
                    ->whereKey($target->deployment_id)
                    ->firstOrFail();
                $deployment->update([
                    'product_version_id' => $campaign->target_version_id,
                    'last_confirmed_at' => $now,
                ]);
            }

            $fresh = $target->fresh(['deployment.customer', 'deployment.productVersion', 'campaign']);
            AuditLogger::logCampaignTargetUpdated($fresh, $actor, $previousStatus);

            if (
                $status === PatchCampaignTargetStatus::Updated
                || $status === PatchCampaignTargetStatus::Excepted
            ) {
                $this->maybeCompleteCampaign($campaign->fresh(), $actor);
            }

            return $fresh->fresh(['deployment.customer', 'deployment.productVersion', 'campaign']);
        });
    }

    /**
     * Complete an active campaign when every target is updated or excepted
     * (including campaigns with zero targets after activate).
     */
    public function maybeCompleteCampaign(PatchCampaign $campaign, User $actor): void
    {
        if ($campaign->status !== PatchCampaignStatus::Active) {
            return;
        }

        $openTargets = $campaign->targets()
            ->whereNotIn('status', [
                PatchCampaignTargetStatus::Updated->value,
                PatchCampaignTargetStatus::Excepted->value,
            ])
            ->count();

        if ($openTargets > 0) {
            return;
        }

        $campaign->update([
            'status' => PatchCampaignStatus::Completed,
            'completed_at' => now(),
        ]);

        AuditLogger::logPatchCampaignCompleted(
            $campaign->fresh(['targetVersion', 'product']),
            $actor,
        );
    }

    /**
     * Queue stub email notifications for pending targets that have a contact email.
     *
     * @return array{queued: int, skipped_no_email: int}
     */
    public function queueCustomerNotifications(PatchCampaign $campaign, User $actor): array
    {
        $this->assertActive($campaign);

        if (!config('customer_notifications.enabled')) {
            throw ValidationException::withMessages([
                'notifications' => [Translations::get('products.campaigns.notifications_disabled')],
            ]);
        }

        $campaign->loadMissing(['targets.deployment.customer']);

        $queued = 0;
        $skippedNoEmail = 0;

        foreach ($campaign->targets as $target) {
            if ($target->status !== PatchCampaignTargetStatus::Pending) {
                continue;
            }

            $email = CustomerContactEmail::extract(
                $target->deployment?->customer?->primary_contact,
            );

            if ($email === null) {
                $skippedNoEmail++;

                continue;
            }

            SendPatchCampaignCustomerNotificationJob::dispatch($target->id, $actor->id);
            $queued++;
        }

        AuditLogger::logPatchCampaignNotificationsQueued(
            $campaign->fresh(['targetVersion', 'product']),
            $actor,
            $queued,
            $skippedNoEmail,
        );

        return [
            'queued' => $queued,
            'skipped_no_email' => $skippedNoEmail,
        ];
    }

    /**
     * Deployments for the product where version is null or not the campaign target.
     *
     * @return list<int>
     */
    public function matchingDeploymentIds(int $productId, int $targetVersionId): array
    {
        return ProductDeployment::query()
            ->where('product_id', $productId)
            ->where(function ($query) use ($targetVersionId): void {
                $query
                    ->whereNull('product_version_id')
                    ->orWhere('product_version_id', '!=', $targetVersionId);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     target_version_id: int,
     *     target_version_number: string|null,
     *     product_vulnerability_id: int|null,
     *     vulnerability_title: string|null,
     *     notes: string|null,
     *     started_at: string|null,
     *     completed_at: string|null,
     *     created_by: int|null,
     *     targets: list<array{
     *         id: int,
     *         deployment_id: int,
     *         customer_name: string,
     *         environment: string,
     *         version_number: string|null,
     *         status: string,
     *         notified_at: string|null,
     *         acknowledged_at: string|null,
     *         confirmed_at: string|null,
     *         notification_note: string|null
     *     }>
     * }
     */
    public function showPayload(PatchCampaign $campaign): array
    {
        $campaign->loadMissing([
            'targetVersion',
            'productVulnerability',
            'targets.deployment.customer',
            'targets.deployment.productVersion',
        ]);

        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'status' => $campaign->status->value,
            'target_version_id' => $campaign->target_version_id,
            'target_version_number' => $campaign->targetVersion?->version_number,
            'product_vulnerability_id' => $campaign->product_vulnerability_id,
            'vulnerability_title' => $campaign->productVulnerability?->title,
            'notes' => $campaign->notes,
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
            'created_by' => $campaign->created_by,
            'targets' => $campaign->targets
                ->sortBy('id')
                ->values()
                ->map(fn(PatchCampaignTarget $target) => [
                    'id' => $target->id,
                    'deployment_id' => $target->deployment_id,
                    'customer_name' => $target->deployment?->customer?->name ?? '',
                    'environment' => $target->deployment?->environment->value ?? '',
                    'version_number' => $target->deployment?->productVersion?->version_number,
                    'status' => $target->status->value,
                    'notified_at' => $target->notified_at?->toIso8601String(),
                    'acknowledged_at' => $target->acknowledged_at?->toIso8601String(),
                    'confirmed_at' => $target->confirmed_at?->toIso8601String(),
                    'notification_note' => $target->notification_note,
                ])
                ->all(),
        ];
    }

    private function assertDraft(PatchCampaign $campaign): void
    {
        if ($campaign->status !== PatchCampaignStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.campaigns.only_draft')],
            ]);
        }
    }

    private function assertActive(PatchCampaign $campaign): void
    {
        if ($campaign->status !== PatchCampaignStatus::Active) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.campaigns.only_active_targets')],
            ]);
        }
    }

    private function assertTargetVersionBelongsToProduct(Product $product, int $versionId): void
    {
        $exists = ProductVersion::query()
            ->whereKey($versionId)
            ->where('product_id', $product->id)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'target_version_id' => [Translations::get('products.campaigns.version_invalid')],
            ]);
        }
    }

    private function assertVulnerabilityBelongsToProduct(Product $product, ?int $vulnerabilityId): void
    {
        if ($vulnerabilityId === null) {
            return;
        }

        $exists = ProductVulnerability::query()
            ->whereKey($vulnerabilityId)
            ->where('product_id', $product->id)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'product_vulnerability_id' => [Translations::get('products.campaigns.vulnerability_invalid')],
            ]);
        }
    }

    /**
     * Stream an XLSX of campaign targets (affected customer installations).
     */
    public function exportAffectedCustomersXlsx(PatchCampaign $campaign, User $actor): StreamedResponse
    {
        $campaign->loadMissing([
            'targetVersion',
            'targets.deployment.customer',
            'targets.deployment.productVersion',
        ]);

        $headers = [
            Translations::get('products.campaigns.export.columns.customer_name'),
            Translations::get('products.campaigns.export.columns.external_ref'),
            Translations::get('products.campaigns.export.columns.primary_contact'),
            Translations::get('products.campaigns.export.columns.criticality'),
            Translations::get('products.campaigns.export.columns.environment'),
            Translations::get('products.campaigns.export.columns.current_version'),
            Translations::get('products.campaigns.export.columns.target_version'),
            Translations::get('products.campaigns.export.columns.status'),
            Translations::get('products.campaigns.export.columns.notified_at'),
            Translations::get('products.campaigns.export.columns.acknowledged_at'),
            Translations::get('products.campaigns.export.columns.confirmed_at'),
            Translations::get('products.campaigns.export.columns.notification_note'),
            Translations::get('products.campaigns.export.columns.internet_exposure'),
        ];

        $dataRows = $campaign->targets
            ->sortBy('id')
            ->values()
            ->map(function (PatchCampaignTarget $target) use ($campaign): array {
                $deployment = $target->deployment;
                $customer = $deployment?->customer;

                return [
                    $customer?->name ?? '',
                    $customer?->external_ref ?? '',
                    $customer?->primary_contact ?? '',
                    $customer?->criticality?->value ?? '',
                    $deployment?->environment->value ?? '',
                    $deployment?->productVersion?->version_number ?? '',
                    $campaign->targetVersion?->version_number ?? '',
                    $target->status->value,
                    $target->notified_at?->format('d.m.Y H:i') ?? '',
                    $target->acknowledged_at?->format('d.m.Y H:i') ?? '',
                    $target->confirmed_at?->format('d.m.Y H:i') ?? '',
                    $target->notification_note ?? '',
                    $deployment?->internet_exposure
                    ? Translations::get('common.yes')
                    : Translations::get('common.no'),
                ];
            })
            ->all();

        AuditLogger::logPatchCampaignExported($campaign, $actor, count($dataRows));

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('products.campaigns.export.sheet_title'));
        $sheet->fromArray(array_merge([$headers], $dataRows), null, 'A1');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A:M')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = sprintf(
            'campaign-%d-affected-customers-%s.xlsx',
            $campaign->id,
            now()->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     target_version_id: int,
     *     target_version_number: string|null,
     *     product_vulnerability_id: int|null,
     *     vulnerability_title: string|null,
     *     targets_count: int,
     *     started_at: string|null,
     *     completed_at: string|null,
     *     created_at: string|null
     * }
     */
    private function listItemPayload(PatchCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'title' => $campaign->title,
            'status' => $campaign->status->value,
            'target_version_id' => $campaign->target_version_id,
            'target_version_number' => $campaign->targetVersion?->version_number,
            'product_vulnerability_id' => $campaign->product_vulnerability_id,
            'vulnerability_title' => $campaign->productVulnerability?->title,
            'targets_count' => (int) ($campaign->targets_count ?? $campaign->targets()->count()),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
            'created_at' => $campaign->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Support\AuditLogger;
use App\Support\Translations;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceService
{
    public function __construct(
        private readonly UserSecurityInstructionExportService $userSecurityInstructionExports,
    ) {
    }

    public static function deriveFreshness(
        EvidenceFreshnessStatus $current,
        CarbonInterface|string|null $validUntil,
        CarbonInterface|string|null $reviewDueAt,
        ?CarbonInterface $now = null,
    ): EvidenceFreshnessStatus {
        if (in_array($current, [EvidenceFreshnessStatus::Superseded, EvidenceFreshnessStatus::Invalid], true)) {
            return $current;
        }

        $now ??= now();
        $validUntilAt = self::asCarbon($validUntil);
        $reviewDueAtAt = self::asCarbon($reviewDueAt);

        if ($validUntilAt !== null && $validUntilAt->copy()->endOfDay()->lt($now)) {
            return EvidenceFreshnessStatus::Expired;
        }

        if ($reviewDueAtAt !== null && $reviewDueAtAt->copy()->startOfDay()->lte($now->copy()->startOfDay())) {
            return EvidenceFreshnessStatus::ReviewDue;
        }

        return EvidenceFreshnessStatus::Current;
    }

    private static function asCarbon(CarbonInterface|string|null $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = Evidence::query()
            ->where('product_id', $product->id)
            ->with(['owner', 'productVersion']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('freshness_status', 'like', "%{$search}%")
                    ->orWhere('checksum_sha256', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'type' => 'type',
            'freshness_status' => 'freshness_status',
            'collected_at' => 'collected_at',
            'valid_until' => 'valid_until',
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (Evidence $evidence) {
                $this->applyDerivedFreshness($evidence);

                return $this->listItemPayload($evidence);
            });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $requirementIds
     * @param  list<int>  $controlIds
     * @param  list<int>  $riskIds
     * @param  list<int>  $vulnerabilityIds
     */
    public function create(
        Product $product,
        array $attributes,
        ?UploadedFile $file,
        User $uploader,
        array $requirementIds,
        array $controlIds,
        array $riskIds,
        array $vulnerabilityIds,
    ): Evidence {
        $evidence = DB::transaction(function () use ($product, $attributes, $file, $uploader, $requirementIds, $controlIds, $riskIds, $vulnerabilityIds) {
            $this->assertLinksBelongToProduct($product, $controlIds, $riskIds, $vulnerabilityIds);
            $this->assertSupersedesBelongsToProduct($product, $attributes['supersedes_evidence_id'] ?? null);

            $fileMeta = $this->storeFile($product, $file);

            $requestedStatus = $attributes['freshness_status'] instanceof EvidenceFreshnessStatus
                ? $attributes['freshness_status']
                : EvidenceFreshnessStatus::from((string) ($attributes['freshness_status'] ?? EvidenceFreshnessStatus::Current->value));

            $freshness = self::deriveFreshness(
                $requestedStatus,
                $attributes['valid_until'] ?? null,
                $attributes['review_due_at'] ?? null,
            );

            /** @var Evidence $evidence */
            $evidence = Evidence::query()->create([
                ...$attributes,
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'storage_path' => $fileMeta['storage_path'],
                'source_filename' => $fileMeta['source_filename'],
                'checksum_sha256' => $fileMeta['checksum_sha256'],
                'uploaded_by' => $uploader->id,
                'freshness_status' => $freshness,
            ]);

            $this->syncLinks($evidence, $requirementIds, $controlIds, $riskIds, $vulnerabilityIds);

            return $evidence->load(['owner', 'productVersion', 'requirements', 'controls', 'risks', 'vulnerabilities']);
        });

        AuditLogger::logEvidenceCreated($evidence, $uploader);

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function createIntegrationSnapshot(
        Product $product,
        array $snapshot,
        string $title,
        string $source,
        ?User $uploader = null,
        ?string $notes = null,
    ): Evidence {
        $json = json_encode(
            $snapshot,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if ($json === false) {
            throw new \RuntimeException('Failed to encode integration snapshot JSON.');
        }

        $filename = 'vcs-sync-' . now()->format('Ymd-His') . '.json';
        $storagePath = "evidence/{$product->id}/" . uniqid('ev_', true) . '_' . $filename;
        Storage::disk('local')->put($storagePath, $json);

        $evidence = Evidence::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'type' => EvidenceType::IntegrationSnapshot,
            'title' => $title,
            'source' => $source,
            'owner_user_id' => $uploader?->id,
            'storage_path' => $storagePath,
            'source_filename' => $filename,
            'checksum_sha256' => hash('sha256', $json),
            'confidentiality' => EvidenceConfidentiality::Internal,
            'collected_at' => now(),
            'freshness_status' => EvidenceFreshnessStatus::Current,
            'uploaded_by' => $uploader?->id,
            'notes' => $notes,
        ]);

        if ($uploader !== null) {
            AuditLogger::logEvidenceCreated($evidence, $uploader);
        }

        return $evidence;
    }

    /**
     * Persist an approved org policy as Markdown evidence (type=policy) on a product.
     */
    public function createFromOrgPolicy(Product $product, OrgPolicy $policy, User $uploader): Evidence
    {
        if ($product->organization_id !== $policy->organization_id) {
            throw ValidationException::withMessages([
                'product_id' => [Translations::get('policies.publish_product_invalid')],
            ]);
        }

        $safeVersion = preg_replace('/[^A-Za-z0-9._-]+/', '-', $policy->version_label) ?: '1';
        $filename = sprintf('policy-%s-v%s.md', $policy->policy_type->value, $safeVersion);
        $storagePath = "evidence/{$product->id}/" . uniqid('ev_', true) . '_' . $filename;
        $body = $policy->body;

        Storage::disk('local')->put($storagePath, $body);

        $evidence = Evidence::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'type' => EvidenceType::Policy,
            'title' => $policy->title . ' (' . $policy->version_label . ')',
            'source' => 'org_policy:' . $policy->id,
            'owner_user_id' => $uploader->id,
            'storage_path' => $storagePath,
            'source_filename' => $filename,
            'checksum_sha256' => hash('sha256', $body),
            'confidentiality' => EvidenceConfidentiality::Internal,
            'collected_at' => $policy->approved_at ?? now(),
            'freshness_status' => EvidenceFreshnessStatus::Current,
            'uploaded_by' => $uploader->id,
            'notes' => 'Published from organization policy library (type: '
                . $policy->policy_type->value . ').',
        ]);

        AuditLogger::logEvidenceCreated($evidence, $uploader);

        return $evidence;
    }

    /**
     * Persist published user security instructions as Markdown evidence (type=document).
     */
    public function createFromUserSecurityInstruction(
        Product $product,
        UserSecurityInstruction $instruction,
        User $uploader,
    ): Evidence {
        if ($product->id !== $instruction->product_id) {
            throw ValidationException::withMessages([
                'product_id' => [Translations::get('products.user_security_instructions.publish_product_invalid')],
            ]);
        }

        $product->loadMissing('organization');
        $organization = $product->organization;

        $safeVersion = preg_replace('/[^A-Za-z0-9._-]+/', '-', $instruction->version_label) ?: '1';
        $safeLocale = preg_replace('/[^A-Za-z0-9._-]+/', '-', $instruction->locale) ?: 'en';
        $filename = sprintf('user-security-instructions-v%s-%s.md', $safeVersion, $safeLocale);
        $storagePath = "evidence/{$product->id}/" . uniqid('ev_', true) . '_' . $filename;
        $body = $this->userSecurityInstructionExports->toMarkdown(
            $instruction,
            $product,
            $organization,
        );

        Storage::disk('local')->put($storagePath, $body);

        $evidence = Evidence::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'product_version_id' => $instruction->product_version_id,
            'type' => EvidenceType::Document,
            'title' => $instruction->title . ' (' . $instruction->version_label . ')',
            'source' => 'user_security_instruction:' . $instruction->id,
            'owner_user_id' => $uploader->id,
            'storage_path' => $storagePath,
            'source_filename' => $filename,
            'checksum_sha256' => hash('sha256', $body),
            'confidentiality' => EvidenceConfidentiality::Internal,
            'collected_at' => $instruction->published_at ?? now(),
            'freshness_status' => EvidenceFreshnessStatus::Current,
            'uploaded_by' => $uploader->id,
            'notes' => 'Published from user security instructions (locale: '
                . $instruction->locale . ').',
        ]);

        AuditLogger::logEvidenceCreated($evidence, $uploader);

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $requirementIds
     * @param  list<int>  $controlIds
     * @param  list<int>  $riskIds
     * @param  list<int>  $vulnerabilityIds
     */
    public function update(
        Evidence $evidence,
        array $attributes,
        ?UploadedFile $file,
        array $requirementIds,
        array $controlIds,
        array $riskIds,
        array $vulnerabilityIds,
    ): Evidence {
        $evidence = DB::transaction(function () use ($evidence, $attributes, $file, $requirementIds, $controlIds, $riskIds, $vulnerabilityIds) {
            $this->assertLinksBelongToProduct(
                $evidence->product,
                $controlIds,
                $riskIds,
                $vulnerabilityIds,
            );
            $this->assertSupersedesBelongsToProduct(
                $evidence->product,
                $attributes['supersedes_evidence_id'] ?? null,
                $evidence->id,
            );

            $payload = $attributes;

            if ($file !== null) {
                $fileMeta = $this->storeFile($evidence->product, $file);
                if ($evidence->storage_path) {
                    Storage::disk('local')->delete($evidence->storage_path);
                }
                $payload = [
                    ...$payload,
                    ...$fileMeta,
                ];
            }

            $requestedStatus = $payload['freshness_status'] instanceof EvidenceFreshnessStatus
                ? $payload['freshness_status']
                : EvidenceFreshnessStatus::from((string) $payload['freshness_status']);

            $payload['freshness_status'] = self::deriveFreshness(
                $requestedStatus,
                $payload['valid_until'] ?? null,
                $payload['review_due_at'] ?? null,
            );

            $evidence->update($payload);
            $this->syncLinks($evidence, $requirementIds, $controlIds, $riskIds, $vulnerabilityIds);

            return $evidence->fresh(['owner', 'productVersion', 'requirements', 'controls', 'risks', 'vulnerabilities']);
        });

        $actor = Auth::user();
        if ($actor instanceof User) {
            AuditLogger::logEvidenceUpdated($evidence, $actor);
        }

        return $evidence;
    }

    public function delete(Evidence $evidence): void
    {
        $actor = Auth::user();
        if ($actor instanceof User) {
            AuditLogger::logEvidenceDeleted($evidence, $actor);
        }

        if ($evidence->storage_path) {
            Storage::disk('local')->delete($evidence->storage_path);
        }

        $evidence->delete();
    }

    public function download(Evidence $evidence): StreamedResponse
    {
        $disk = Storage::disk('local');

        if (!$disk instanceof FilesystemAdapter) {
            abort(500, 'Local evidence disk is misconfigured.');
        }

        if ($evidence->storage_path === null || !$disk->exists($evidence->storage_path)) {
            abort(404, 'Evidence file not found.');
        }

        return $disk->download(
            $evidence->storage_path,
            $evidence->source_filename ?? basename($evidence->storage_path),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function listItemPayload(Evidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'title' => $evidence->title,
            'type' => $evidence->type->value,
            'freshness_status' => $evidence->freshness_status->value,
            'version_number' => $evidence->productVersion?->version_number,
            'owner_name' => $evidence->owner?->name,
            'collected_at' => $evidence->collected_at?->toIso8601String(),
            'checksum_short' => $evidence->checksum_sha256
                ? substr($evidence->checksum_sha256, 0, 12)
                : null,
            'has_file' => $evidence->storage_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(Evidence $evidence): array
    {
        $this->applyDerivedFreshness($evidence);

        return [
            'id' => $evidence->id,
            'title' => $evidence->title,
            'type' => $evidence->type->value,
            'source' => $evidence->source,
            'owner_user_id' => $evidence->owner_user_id,
            'product_version_id' => $evidence->product_version_id,
            'version_number' => $evidence->productVersion?->version_number,
            'confidentiality' => $evidence->confidentiality->value,
            'collected_at' => $evidence->collected_at?->format('Y-m-d\TH:i'),
            'valid_until' => $evidence->valid_until?->toDateString(),
            'review_due_at' => $evidence->review_due_at?->toDateString(),
            'freshness_status' => $evidence->freshness_status->value,
            'supersedes_evidence_id' => $evidence->supersedes_evidence_id,
            'source_filename' => $evidence->source_filename,
            'checksum_sha256' => $evidence->checksum_sha256,
            'has_file' => $evidence->storage_path !== null,
            'reviewer_user_id' => $evidence->reviewer_user_id,
            'reviewed_at' => $evidence->reviewed_at?->format('Y-m-d\TH:i'),
            'review_notes' => $evidence->review_notes,
            'notes' => $evidence->notes,
            'requirement_ids' => $evidence->requirements->pluck('id')->all(),
            'control_ids' => $evidence->controls->pluck('id')->all(),
            'risk_ids' => $evidence->risks->pluck('id')->all(),
            'vulnerability_ids' => $evidence->vulnerabilities->pluck('id')->all(),
        ];
    }

    private function applyDerivedFreshness(Evidence $evidence): void
    {
        $derived = self::deriveFreshness(
            $evidence->freshness_status,
            $evidence->valid_until,
            $evidence->review_due_at,
        );

        if ($derived !== $evidence->freshness_status) {
            $evidence->freshness_status = $derived;
            $evidence->saveQuietly();
        }
    }

    /**
     * Persist derived freshness for evidence rows whose dates have elapsed.
     * Manual overrides (superseded / invalid) are left unchanged.
     *
     * @return array{scanned: int, updated: int}
     */
    public function refreshDerivedFreshnessStatuses(
        ?int $organizationId = null,
        bool $dryRun = false,
        ?CarbonInterface $now = null,
    ): array {
        $scanned = 0;
        $updated = 0;
        $now ??= now();

        $query = Evidence::query()->orderBy('id');

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $query->chunkById(200, function ($rows) use (&$scanned, &$updated, $dryRun, $now): void {
            foreach ($rows as $evidence) {
                /** @var Evidence $evidence */
                $scanned++;

                $derived = self::deriveFreshness(
                    $evidence->freshness_status,
                    $evidence->valid_until,
                    $evidence->review_due_at,
                    $now,
                );

                if ($derived === $evidence->freshness_status) {
                    continue;
                }

                $updated++;

                if (!$dryRun) {
                    $evidence->freshness_status = $derived;
                    $evidence->saveQuietly();
                }
            }
        });

        return [
            'scanned' => $scanned,
            'updated' => $updated,
        ];
    }

    /**
     * @return array{storage_path: string|null, source_filename: string|null, checksum_sha256: string|null}
     */
    private function storeFile(Product $product, ?UploadedFile $file): array
    {
        if ($file === null) {
            return [
                'storage_path' => null,
                'source_filename' => null,
                'checksum_sha256' => null,
            ];
        }

        $contents = $file->get();

        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is empty or unreadable.',
            ]);
        }

        $filename = $file->getClientOriginalName();
        $storagePath = "evidence/{$product->id}/" . uniqid('ev_', true) . '_' . $filename;
        Storage::disk('local')->put($storagePath, $contents);

        return [
            'storage_path' => $storagePath,
            'source_filename' => $filename,
            'checksum_sha256' => hash('sha256', $contents),
        ];
    }

    /**
     * @param  list<int>  $requirementIds
     * @param  list<int>  $controlIds
     * @param  list<int>  $riskIds
     * @param  list<int>  $vulnerabilityIds
     */
    private function syncLinks(
        Evidence $evidence,
        array $requirementIds,
        array $controlIds,
        array $riskIds,
        array $vulnerabilityIds,
    ): void {
        $evidence->requirements()->sync($this->uniqueIds($requirementIds));
        $evidence->controls()->sync($this->uniqueIds($controlIds));
        $evidence->risks()->sync($this->uniqueIds($riskIds));
        $evidence->vulnerabilities()->sync($this->uniqueIds($vulnerabilityIds));
    }

    /**
     * @param  list<int>  $controlIds
     * @param  list<int>  $riskIds
     * @param  list<int>  $vulnerabilityIds
     */
    private function assertLinksBelongToProduct(
        Product $product,
        array $controlIds,
        array $riskIds,
        array $vulnerabilityIds,
    ): void {
        $controls = $this->uniqueIds($controlIds);
        if ($controls !== []) {
            $valid = Control::query()
                ->where('organization_id', $product->organization_id)
                ->whereIn('id', $controls)
                ->count();
            if ($valid !== count($controls)) {
                throw ValidationException::withMessages([
                    'control_ids' => 'One or more controls are invalid for this organization.',
                ]);
            }
        }

        $risks = $this->uniqueIds($riskIds);
        if ($risks !== []) {
            $valid = ProductRisk::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $risks)
                ->count();
            if ($valid !== count($risks)) {
                throw ValidationException::withMessages([
                    'risk_ids' => 'One or more risks are invalid for this product.',
                ]);
            }
        }

        $vulns = $this->uniqueIds($vulnerabilityIds);
        if ($vulns !== []) {
            $valid = ProductVulnerability::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $vulns)
                ->count();
            if ($valid !== count($vulns)) {
                throw ValidationException::withMessages([
                    'vulnerability_ids' => 'One or more vulnerabilities are invalid for this product.',
                ]);
            }
        }

        // Requirements are global catalogue — existence checked in FormRequest.
    }

    private function assertSupersedesBelongsToProduct(
        Product $product,
        mixed $supersedesId,
        ?int $exceptId = null,
    ): void {
        if ($supersedesId === null || $supersedesId === '') {
            return;
        }

        $query = Evidence::query()
            ->where('product_id', $product->id)
            ->where('id', (int) $supersedesId);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        if (!$query->exists()) {
            throw ValidationException::withMessages([
                'supersedes_evidence_id' => 'The superseded evidence must belong to this product.',
            ]);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}

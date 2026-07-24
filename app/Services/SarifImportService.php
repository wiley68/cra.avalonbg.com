<?php

namespace App\Services;

use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Models\Evidence;
use App\Models\IntegrationSyncRun;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\Integrations\SarifFindingParser;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SarifImportService
{
    public function __construct(
        private readonly SarifFindingParser $parser,
        private readonly ImportSuggestionService $suggestions,
        private readonly EvidenceService $evidence,
        private readonly ProductIntegrationLinkService $links,
    ) {
    }

    public function import(
        Product $product,
        UploadedFile $file,
        User $actor,
    ): IntegrationSyncRun {
        $link = $this->links->ensureSarifLink($product, $actor);
        $link->loadMissing(['integration', 'product']);

        $run = IntegrationSyncRun::query()->create([
            'link_id' => $link->id,
            'status' => IntegrationSyncRunStatus::Running,
            'triggered_by' => $actor->id,
            'started_at' => now(),
        ]);

        try {
            $rawEvidence = $this->storeRawScan($product, $file, $actor);

            $parsed = $this->parser->tryParse($file);
            $findings = $parsed['findings'];
            $suggestionStats = $this->suggestions->upsertVulnerabilitySuggestionsFromScanner($link, $findings);

            $summary = [
                'provider' => IntegrationProvider::Sarif->value,
                'tool_name' => $parsed['tool_name'],
                'runs_count' => $parsed['runs_count'],
                'findings_count' => count($findings),
                'source_filename' => $file->getClientOriginalName(),
                'raw_evidence_id' => $rawEvidence->id,
                'synced_at' => now()->toIso8601String(),
                'sync_run_id' => $run->id,
                ...$suggestionStats,
            ];

            if ($parsed['soft_fail'] === true) {
                $summary['soft_fail'] = true;
                $summary['last_error'] = $parsed['last_error'];
            }

            $snapshot = $this->evidence->createIntegrationSnapshot(
                product: $product,
                snapshot: $summary,
                title: 'SARIF import — ' . ($parsed['tool_name'] ?: 'upload') . ' — ' . now()->format('Y-m-d H:i'),
                source: 'sarif:upload',
                uploader: $actor,
                notes: 'Auto-created from SARIF import run #' . $run->id,
                filenamePrefix: 'sarif-import',
            );

            $summary['evidence_id'] = $snapshot->id;
            $summary['evidence_checksum_sha256'] = $snapshot->checksum_sha256;

            $link->update([
                'last_synced_at' => now(),
                'last_sync_summary' => $summary,
                'external_label' => $parsed['tool_name']
                    ? 'SARIF (' . $parsed['tool_name'] . ')'
                    : ($link->external_label ?: 'SARIF uploads'),
            ]);

            $run->update([
                'status' => IntegrationSyncRunStatus::Succeeded,
                'finished_at' => now(),
                'summary' => $summary,
            ]);

            AuditLogger::logIntegrationSyncSucceeded(
                $link->fresh(['product', 'integration']),
                $run->fresh(),
                $actor,
            );

            return $run->fresh();
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $errorSummary = [
                'provider' => IntegrationProvider::Sarif->value,
                'error' => $message,
                'last_error' => $message,
            ];

            $link->update([
                'last_synced_at' => now(),
                'last_sync_summary' => array_merge(
                    is_array($link->last_sync_summary) ? $link->last_sync_summary : [],
                    $errorSummary,
                ),
            ]);

            $run->update([
                'status' => IntegrationSyncRunStatus::Failed,
                'finished_at' => now(),
                'summary' => $errorSummary,
            ]);

            AuditLogger::logIntegrationSyncFailed(
                $link->fresh(['product', 'integration']),
                $run->fresh(),
                $actor,
                $message,
            );

            return $run->fresh();
        }
    }

    private function storeRawScan(Product $product, UploadedFile $file, User $actor): Evidence
    {
        $original = $file->getClientOriginalName() ?: 'scan.sarif.json';
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original) ?: 'scan.sarif.json';
        $storagePath = "evidence/{$product->id}/" . uniqid('sarif_', true) . '_' . $safeName;
        $contents = (string) file_get_contents($file->getRealPath() ?: '');

        Storage::disk('local')->put($storagePath, $contents);

        $evidence = Evidence::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'type' => EvidenceType::VulnerabilityScan,
            'title' => 'SARIF upload — ' . $original,
            'source' => 'sarif:upload',
            'owner_user_id' => $actor->id,
            'storage_path' => $storagePath,
            'source_filename' => $original,
            'checksum_sha256' => hash('sha256', $contents),
            'confidentiality' => EvidenceConfidentiality::Internal,
            'collected_at' => now(),
            'freshness_status' => EvidenceFreshnessStatus::Current,
            'uploaded_by' => $actor->id,
            'notes' => 'Uploaded SARIF artifact for scanner import suggestions.',
        ]);

        AuditLogger::logEvidenceCreated($evidence, $actor);

        return $evidence;
    }
}

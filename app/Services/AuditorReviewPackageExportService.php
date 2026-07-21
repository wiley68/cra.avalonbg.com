<?php

namespace App\Services;

use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class AuditorReviewPackageExportService
{
    public function __construct(
        private readonly ProductReadinessService $readiness,
        private readonly AuditorFindingService $findings,
    ) {
    }

    public function downloadZip(
        AuditorReviewPackage $package,
        Organization $organization,
        User $actor,
    ): BinaryFileResponse {
        $package->loadMissing([
            'product.productOwner:id,name,email',
            'product.securityContact:id,name,email',
            'product.versions' => fn($query) => $query
                ->orderByDesc('release_date')
                ->orderByDesc('id')
                ->limit(5),
            'product.supportPeriods' => fn($query) => $query
                ->orderBy('type')
                ->orderByDesc('id'),
            'creator:id,name',
            'evidence',
            'findings' => fn($query) => $query
                ->with('creator:id,name')
                ->orderByDesc('id'),
        ]);

        $product = $package->product;
        $report = $this->readiness->build($product);

        $pdfBinary = Pdf::loadView('pdf.auditor-review-package', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'package' => [
                'id' => $package->id,
                'title' => $package->title,
                'status' => $package->status->value,
                'notes' => $package->notes,
                'shared_at' => $package->shared_at?->toIso8601String(),
                'closed_at' => $package->closed_at?->toIso8601String(),
                'created_by_name' => $package->creator?->name,
            ],
            'product' => $this->productPayload($product),
            'report' => $report,
            'findings' => $package->findings
                ->map(fn(AuditorFinding $finding) => $this->findings->payload($finding))
                ->values()
                ->all(),
            'evidence' => $package->evidence
                ->map(fn(Evidence $evidence) => [
                    'id' => $evidence->id,
                    'title' => $evidence->title,
                    'type' => $evidence->type->value,
                    'freshness_status' => $evidence->freshness_status?->value,
                    'has_file' => $evidence->storage_path !== null,
                    'source_filename' => $evidence->source_filename,
                ])
                ->values()
                ->all(),
            'generated_at' => now()->toIso8601String(),
        ])
            ->setPaper('a4')
            ->output();

        $zipPath = $this->buildZipArchive($package, $pdfBinary);
        $filename = $this->zipFilename($package);

        AuditLogger::logAuditorPackageExported($package, $actor);

        return response()
            ->download($zipPath, $filename, [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    private function buildZipArchive(AuditorReviewPackage $package, string $pdfBinary): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'auditor-package-');
        if ($zipPath === false) {
            abort(500, 'Could not create temporary export file.');
        }

        // ZipArchive requires a .zip extension on some platforms.
        $zipPathWithExt = $zipPath . '.zip';
        rename($zipPath, $zipPathWithExt);
        $zipPath = $zipPathWithExt;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Could not create ZIP archive.');
        }

        $zip->addFromString('review-package.pdf', $pdfBinary);
        $zip->addFromString(
            'evidence/README.txt',
            Translations::get('auditor.export.evidence_readme'),
        );

        $disk = Storage::disk('local');
        $usedNames = [];

        foreach ($package->evidence as $evidence) {
            if ($evidence->storage_path === null || !$disk->exists($evidence->storage_path)) {
                continue;
            }

            $entryName = $this->uniqueEvidenceEntryName($evidence, $usedNames);
            $usedNames[] = strtolower($entryName);
            $zip->addFromString(
                'evidence/' . $entryName,
                $disk->get($evidence->storage_path),
            );
        }

        $zip->close();

        return $zipPath;
    }

    private function uniqueEvidenceEntryName(Evidence $evidence, array $usedNames): string
    {
        $base = $evidence->source_filename
            ?: ('evidence-' . $evidence->id);
        $safe = Str::slug(pathinfo($base, PATHINFO_FILENAME)) ?: 'evidence-' . $evidence->id;
        $ext = pathinfo($base, PATHINFO_EXTENSION);
        $candidate = $ext !== ''
            ? sprintf('%d-%s.%s', $evidence->id, $safe, $ext)
            : sprintf('%d-%s', $evidence->id, $safe);

        $index = 2;
        $current = $candidate;
        while (in_array(strtolower($current), $usedNames, true)) {
            $current = $ext !== ''
                ? sprintf('%d-%s-%d.%s', $evidence->id, $safe, $index, $ext)
                : sprintf('%d-%s-%d', $evidence->id, $safe, $index);
            $index++;
        }

        return $current;
    }

    private function zipFilename(AuditorReviewPackage $package): string
    {
        $slug = Str::slug($package->title) ?: 'review-package';

        return sprintf(
            'auditor-package-%s-%s.zip',
            $slug,
            now()->format('Y-m-d'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     manufacturer: string|null,
     *     trademark: string|null,
     *     product_type: string|null,
     *     licensing_model: string|null,
     *     scope_status: string|null,
     *     classification_status: string|null,
     *     intended_purpose: string|null,
     *     product_owner: array{id: int, name: string, email: string}|null,
     *     security_contact: array{id: int, name: string, email: string}|null,
     *     versions: list<array{id: int, version_number: string, state: string|null, support_status: string|null, release_date: string|null}>,
     *     support_periods: list<array{id: int, type: string, duration_months: int, effective_starts_at: string|null, effective_ends_at: string|null, schedule_resolved: bool}>
     * }
     */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'manufacturer' => $product->manufacturer,
            'trademark' => $product->trademark,
            'product_type' => $product->product_type?->value,
            'licensing_model' => $product->licensing_model?->value,
            'scope_status' => $product->scope_status?->value,
            'classification_status' => $product->classification_status?->value,
            'intended_purpose' => $product->intended_purpose,
            'product_owner' => $product->productOwner
                ? [
                    'id' => $product->productOwner->id,
                    'name' => $product->productOwner->name,
                    'email' => $product->productOwner->email,
                ]
                : null,
            'security_contact' => $product->securityContact
                ? [
                    'id' => $product->securityContact->id,
                    'name' => $product->securityContact->name,
                    'email' => $product->securityContact->email,
                ]
                : null,
            'versions' => $product->versions->map(fn($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'state' => $version->state?->value,
                'support_status' => $version->support_status?->value,
                'release_date' => $version->release_date?->toDateString(),
            ])->values()->all(),
            'support_periods' => $product->supportPeriods->map(fn($period) => [
                'id' => $period->id,
                'type' => $period->type->value,
                'duration_months' => $period->duration_months,
                'effective_starts_at' => $period->effectiveStartsAt()?->toDateString(),
                'effective_ends_at' => $period->effectiveEndsAt()?->toDateString(),
                'schedule_resolved' => $period->scheduleResolved(),
            ])->values()->all(),
        ];
    }
}

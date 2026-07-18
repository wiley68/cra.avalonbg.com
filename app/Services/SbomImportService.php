<?php

namespace App\Services;

use App\Enums\ComponentSupportStatus;
use App\Enums\PackageEcosystem;
use App\Enums\SbomFormat;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Sbom;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SbomImportService
{
    public function __construct(
        private readonly ComponentService $components,
    ) {
    }

    /**
     * @return array{sbom: Sbom, imported: int, updated: int}
     */
    public function import(
        Product $product,
        ProductVersion $version,
        UploadedFile $file,
        User $importer,
        ?SbomFormat $forcedFormat = null,
    ): array {
        if ($version->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'product_version_id' => 'The selected version does not belong to this product.',
            ]);
        }

        $contents = $file->get();

        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'file' => 'The uploaded file is empty or unreadable.',
            ]);
        }

        $format = $forcedFormat ?? $this->detectFormat($file->getClientOriginalName(), $contents);
        $rows = match ($format) {
            SbomFormat::CycloneDxJson => $this->parseCycloneDx($contents),
            SbomFormat::ComposerLock => $this->parseComposerLock($contents),
            SbomFormat::Manual => throw ValidationException::withMessages([
                'format' => 'Manual format cannot be imported from a file.',
            ]),
        };

        $checksum = hash('sha256', $contents);
        $filename = $file->getClientOriginalName();

        return DB::transaction(function () use ($product, $version, $contents, $checksum, $filename, $format, $rows, $importer) {
            $storagePath = "sboms/{$product->id}/" . uniqid('sbom_', true) . '_' . $filename;
            Storage::disk('local')->put($storagePath, $contents);

            $sbom = Sbom::query()->create([
                'product_id' => $product->id,
                'product_version_id' => $version->id,
                'format' => $format,
                'source_filename' => $filename,
                'storage_path' => $storagePath,
                'checksum_sha256' => $checksum,
                'component_count' => 0,
                'imported_by' => $importer->id,
                'imported_at' => now(),
            ]);

            $imported = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $beforeId = $this->findExistingId($version->id, $row);
                $this->components->upsertFromImport($product, $version, $sbom->id, $row);

                if ($beforeId === null) {
                    $imported++;
                } else {
                    $updated++;
                }
            }

            $sbom->update([
                'component_count' => $imported + $updated,
            ]);

            return [
                'sbom' => $sbom->fresh(),
                'imported' => $imported,
                'updated' => $updated,
            ];
        });
    }

    public function detectFormat(string $filename, string $contents): SbomFormat
    {
        $lower = strtolower($filename);

        if (str_ends_with($lower, 'composer.lock') || $lower === 'composer.lock') {
            return SbomFormat::ComposerLock;
        }

        $json = json_decode($contents, true);

        if (!is_array($json)) {
            throw ValidationException::withMessages([
                'file' => 'Unable to detect SBOM format from the uploaded file.',
            ]);
        }

        if (isset($json['bomFormat']) && strcasecmp((string) $json['bomFormat'], 'CycloneDX') === 0) {
            return SbomFormat::CycloneDxJson;
        }

        if (isset($json['packages']) || isset($json['packages-dev'])) {
            return SbomFormat::ComposerLock;
        }

        if (isset($json['components']) && is_array($json['components'])) {
            return SbomFormat::CycloneDxJson;
        }

        throw ValidationException::withMessages([
            'file' => 'Unsupported SBOM format. Use CycloneDX JSON or composer.lock.',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseCycloneDx(string $contents): array
    {
        $json = json_decode($contents, true);

        if (!is_array($json) || !isset($json['components']) || !is_array($json['components'])) {
            throw ValidationException::withMessages([
                'file' => 'Invalid CycloneDX JSON: missing components array.',
            ]);
        }

        $rows = [];

        foreach ($json['components'] as $component) {
            if (!is_array($component)) {
                continue;
            }

            $name = (string) ($component['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $purl = isset($component['purl']) ? (string) $component['purl'] : null;
            $type = strtolower((string) ($component['type'] ?? 'library'));
            $ecosystem = $this->ecosystemFromPurl($purl) ?? PackageEcosystem::Other;

            $licence = null;
            if (isset($component['licenses']) && is_array($component['licenses'])) {
                $first = $component['licenses'][0] ?? null;
                if (is_array($first)) {
                    $licence = $first['license']['id']
                        ?? $first['license']['name']
                        ?? $first['expression']
                        ?? null;
                }
            }

            $hash = null;
            if (isset($component['hashes']) && is_array($component['hashes'])) {
                $firstHash = $component['hashes'][0] ?? null;
                if (is_array($firstHash) && isset($firstHash['content'])) {
                    $alg = (string) ($firstHash['alg'] ?? '');
                    $hash = trim($alg . ' ' . (string) $firstHash['content']);
                }
            }

            $rows[] = [
                'name' => $name,
                'supplier' => is_array($component['supplier'] ?? null)
                    ? ($component['supplier']['name'] ?? null)
                    : null,
                'package_ecosystem' => $ecosystem,
                'version' => isset($component['version']) ? (string) $component['version'] : null,
                'licence' => is_string($licence) ? $licence : null,
                'purl' => $purl,
                'hash' => $hash,
                'is_direct' => true,
                'is_dev' => false,
                'usage_context' => $type,
                'support_status' => ComponentSupportStatus::Unknown,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseComposerLock(string $contents): array
    {
        $json = json_decode($contents, true);

        if (!is_array($json)) {
            throw ValidationException::withMessages([
                'file' => 'Invalid composer.lock JSON.',
            ]);
        }

        $rows = [];

        foreach (['packages' => false, 'packages-dev' => true] as $key => $isDev) {
            $packages = $json[$key] ?? [];
            if (!is_array($packages)) {
                continue;
            }

            foreach ($packages as $package) {
                if (!is_array($package)) {
                    continue;
                }

                $name = (string) ($package['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $version = isset($package['version']) ? (string) $package['version'] : null;
                $purl = 'pkg:composer/' . ltrim($name, '/') . ($version ? '@' . $version : '');

                $licence = null;
                if (isset($package['license'])) {
                    $licence = is_array($package['license'])
                        ? implode(', ', array_map('strval', $package['license']))
                        : (string) $package['license'];
                }

                $hash = null;
                if (isset($package['dist']['shasum'])) {
                    $hash = 'sha1 ' . (string) $package['dist']['shasum'];
                } elseif (isset($package['dist']['reference'])) {
                    $hash = (string) $package['dist']['reference'];
                }

                $rows[] = [
                    'name' => $name,
                    'supplier' => null,
                    'package_ecosystem' => PackageEcosystem::Composer,
                    'version' => $version,
                    'licence' => $licence,
                    'purl' => $purl,
                    'hash' => $hash,
                    'is_direct' => true,
                    'is_dev' => $isDev,
                    'usage_context' => $isDev ? 'dev' : 'runtime',
                    'support_status' => ComponentSupportStatus::Unknown,
                ];
            }
        }

        return $rows;
    }

    private function ecosystemFromPurl(?string $purl): ?PackageEcosystem
    {
        if ($purl === null || $purl === '') {
            return null;
        }

        return match (true) {
            str_starts_with($purl, 'pkg:composer/') => PackageEcosystem::Composer,
            str_starts_with($purl, 'pkg:npm/') => PackageEcosystem::Npm,
            str_starts_with($purl, 'pkg:nuget/') => PackageEcosystem::Nuget,
            str_starts_with($purl, 'pkg:maven/') => PackageEcosystem::Maven,
            str_starts_with($purl, 'pkg:pypi/') => PackageEcosystem::Pypi,
            default => PackageEcosystem::Other,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function findExistingId(int $versionId, array $row): ?int
    {
        $purl = $row['purl'] ?? null;

        $query = \App\Models\ProductComponent::query()
            ->where('product_version_id', $versionId);

        if (is_string($purl) && $purl !== '') {
            return $query->where('purl', $purl)->value('id');
        }

        return $query
            ->whereNull('purl')
            ->where('package_ecosystem', $row['package_ecosystem'] instanceof PackageEcosystem
                ? $row['package_ecosystem']->value
                : $row['package_ecosystem'])
            ->where('name', $row['name'])
            ->where('version', $row['version'] ?? null)
            ->value('id');
    }
}

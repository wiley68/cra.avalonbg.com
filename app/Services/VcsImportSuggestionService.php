<?php

namespace App\Services;

use App\Enums\ProductVersionState;
use App\Enums\SupportStatus;
use App\Enums\VcsImportSuggestionKind;
use App\Enums\VcsImportSuggestionStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\User;
use App\Models\VcsImportSuggestion;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VcsImportSuggestionService
{
    public function __construct(
        private readonly ProductVulnerabilityService $vulnerabilities,
    ) {
    }

    /**
     * @param  list<array{tag_name: string, name: string|null, body?: string|null, published_at: string|null, html_url: string|null}>  $releases
     * @param  list<array{
     *     external_id: string,
     *     number: int|null,
     *     ghsa_id: string|null,
     *     cve_id: string|null,
     *     summary: string,
     *     severity: string|null,
     *     package_name: string|null,
     *     package_ecosystem: string|null,
     *     html_url: string|null,
     *     created_at: string|null
     * }>  $alerts
     * @return array{
     *     version_suggestions_upserted: int,
     *     vulnerability_suggestions_upserted: int,
     *     pending_version_suggestions: int,
     *     pending_vulnerability_suggestions: int
     * }
     */
    public function upsertFromSync(ProductRepository $repository, array $releases, array $alerts): array
    {
        $repository->loadMissing('product');

        $versionUpserted = $this->upsertVersionSuggestions($repository, $releases);
        $vulnerabilityUpserted = $this->upsertVulnerabilitySuggestions($repository, $alerts);

        return [
            'version_suggestions_upserted' => $versionUpserted,
            'vulnerability_suggestions_upserted' => $vulnerabilityUpserted,
            'pending_version_suggestions' => $this->pendingCount($repository, VcsImportSuggestionKind::Version),
            'pending_vulnerability_suggestions' => $this->pendingCount($repository, VcsImportSuggestionKind::Vulnerability),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     kind: string,
     *     external_id: string,
     *     title: string,
     *     summary: string|null,
     *     html_url: string|null,
     *     severity: string|null,
     *     tag_name: string|null,
     *     cve_id: string|null,
     *     package_name: string|null
     * }>
     */
    public function pendingPayloadForProduct(int $productId): array
    {
        return VcsImportSuggestion::query()
            ->where('product_id', $productId)
            ->where('status', VcsImportSuggestionStatus::Pending)
            ->orderByDesc('id')
            ->get()
            ->map(function (VcsImportSuggestion $suggestion): array {
                $payload = $suggestion->payload;

                return [
                    'id' => $suggestion->id,
                    'kind' => $suggestion->kind->value,
                    'external_id' => $suggestion->external_id,
                    'title' => (string) ($payload['title'] ?? $suggestion->external_id),
                    'summary' => isset($payload['summary']) && is_string($payload['summary'])
                        ? $payload['summary']
                        : null,
                    'html_url' => isset($payload['html_url']) && is_string($payload['html_url'])
                        ? $payload['html_url']
                        : null,
                    'severity' => isset($payload['severity']) && is_string($payload['severity'])
                        ? $payload['severity']
                        : null,
                    'tag_name' => isset($payload['tag_name']) && is_string($payload['tag_name'])
                        ? $payload['tag_name']
                        : null,
                    'cve_id' => isset($payload['cve_id']) && is_string($payload['cve_id'])
                        ? $payload['cve_id']
                        : null,
                    'package_name' => isset($payload['package_name']) && is_string($payload['package_name'])
                        ? $payload['package_name']
                        : null,
                ];
            })
            ->all();
    }

    public function accept(VcsImportSuggestion $suggestion, User $actor): ProductVersion|ProductVulnerability
    {
        if ($suggestion->status !== VcsImportSuggestionStatus::Pending) {
            throw ValidationException::withMessages([
                'suggestion' => ['Suggestion is not pending.'],
            ]);
        }

        $suggestion->loadMissing('product');

        $entity = match ($suggestion->kind) {
            VcsImportSuggestionKind::Version => $this->acceptVersion($suggestion),
            VcsImportSuggestionKind::Vulnerability => $this->acceptVulnerability($suggestion),
        };

        $suggestion->update([
            'status' => VcsImportSuggestionStatus::Accepted,
            'accepted_entity_type' => $entity instanceof ProductVersion
                ? ProductVersion::class
                : ProductVulnerability::class,
            'accepted_entity_id' => $entity->id,
        ]);

        AuditLogger::logVcsSuggestionAccepted($suggestion->fresh(), $actor);

        return $entity;
    }

    public function dismiss(VcsImportSuggestion $suggestion, User $actor): void
    {
        if ($suggestion->status !== VcsImportSuggestionStatus::Pending) {
            throw ValidationException::withMessages([
                'suggestion' => ['Suggestion is not pending.'],
            ]);
        }

        $suggestion->update([
            'status' => VcsImportSuggestionStatus::Dismissed,
        ]);

        AuditLogger::logVcsSuggestionDismissed($suggestion->fresh(), $actor);
    }

    private function acceptVersion(VcsImportSuggestion $suggestion): ProductVersion
    {
        $payload = $suggestion->payload;
        $versionNumber = trim((string) ($payload['version_number'] ?? $payload['tag_name'] ?? ''));

        if ($versionNumber === '') {
            throw new RuntimeException('Version suggestion is missing a version number.');
        }

        if (
            ProductVersion::query()
                ->where('product_id', $suggestion->product_id)
                ->where('version_number', $versionNumber)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'suggestion' => ['A product version with this number already exists.'],
            ]);
        }

        $releaseDate = null;
        if (isset($payload['published_at']) && is_string($payload['published_at']) && $payload['published_at'] !== '') {
            $releaseDate = Carbon::parse($payload['published_at'])->toDateString();
        }

        $changelog = null;
        if (isset($payload['body']) && is_string($payload['body']) && $payload['body'] !== '') {
            $changelog = $payload['body'];
        } elseif (isset($payload['summary']) && is_string($payload['summary']) && $payload['summary'] !== '') {
            $changelog = $payload['summary'];
        } elseif (isset($payload['title']) && is_string($payload['title'])) {
            $changelog = $payload['title'];
        }

        return $suggestion->product->versions()->create([
            'version_number' => $versionNumber,
            'release_date' => $releaseDate,
            'state' => ProductVersionState::Draft,
            'support_status' => SupportStatus::Unknown,
            'git_ref' => $versionNumber,
            'changelog' => $changelog,
        ]);
    }

    private function acceptVulnerability(VcsImportSuggestion $suggestion): ProductVulnerability
    {
        $payload = $suggestion->payload;
        $title = trim((string) ($payload['title'] ?? 'Dependabot alert'));
        $summary = isset($payload['summary']) && is_string($payload['summary'])
            ? $payload['summary']
            : null;
        $cveId = isset($payload['cve_id']) && is_string($payload['cve_id'])
            ? $payload['cve_id']
            : null;
        $advisoryUrl = isset($payload['html_url']) && is_string($payload['html_url'])
            ? $payload['html_url']
            : null;

        if (
            $advisoryUrl === null
            && isset($payload['ghsa_id'])
            && is_string($payload['ghsa_id'])
            && $payload['ghsa_id'] !== ''
        ) {
            $advisoryUrl = 'https://github.com/advisories/' . $payload['ghsa_id'];
        }

        $discoveredAt = null;
        if (isset($payload['created_at']) && is_string($payload['created_at']) && $payload['created_at'] !== '') {
            $discoveredAt = Carbon::parse($payload['created_at']);
        }

        $notes = [];
        if (isset($payload['ghsa_id']) && is_string($payload['ghsa_id'])) {
            $notes[] = 'GHSA: ' . $payload['ghsa_id'];
        }
        if (isset($payload['package_name']) && is_string($payload['package_name'])) {
            $notes[] = 'Package: ' . $payload['package_name'];
        }
        if (isset($payload['package_ecosystem']) && is_string($payload['package_ecosystem'])) {
            $notes[] = 'Ecosystem: ' . $payload['package_ecosystem'];
        }
        $notes[] = 'Imported from VCS suggestion #' . $suggestion->id;

        return $this->vulnerabilities->create(
            product: $suggestion->product,
            attributes: [
                'title' => mb_substr($title, 0, 255),
                'summary' => $summary,
                'cve_id' => $cveId,
                'advisory_url' => $advisoryUrl,
                'discovery_source' => VulnerabilityDiscoverySource::DependencyScanner,
                'discovered_at' => $discoveredAt,
                'awareness_at' => null,
                'status' => VulnerabilityStatus::Reported,
                'cvss_score' => null,
                'business_severity' => $this->mapSeverity($payload['severity'] ?? null),
                'exploitation_status' => VulnerabilityExploitationStatus::Unknown,
                'is_public' => false,
                'workaround' => null,
                'corrective_action' => null,
                'owner_user_id' => null,
                'substitute_owner_user_id' => null,
                'corrective_measure_available_at' => null,
                'notes' => implode("\n", $notes),
            ],
            componentIds: [],
            affectedVersionIds: [],
            fixedVersionIds: [],
        );
    }

    private function mapSeverity(mixed $severity): VulnerabilityBusinessSeverity
    {
        if (!is_string($severity)) {
            return VulnerabilityBusinessSeverity::Medium;
        }

        return match (strtolower($severity)) {
            'critical' => VulnerabilityBusinessSeverity::Critical,
            'high' => VulnerabilityBusinessSeverity::High,
            'medium', 'moderate' => VulnerabilityBusinessSeverity::Medium,
            'low' => VulnerabilityBusinessSeverity::Low,
            default => VulnerabilityBusinessSeverity::Medium,
        };
    }

    /**
     * @param  list<array{tag_name: string, name: string|null, body?: string|null, published_at: string|null, html_url: string|null}>  $releases
     */
    private function upsertVersionSuggestions(ProductRepository $repository, array $releases): int
    {
        $existingNumbers = ProductVersion::query()
            ->where('product_id', $repository->product_id)
            ->pluck('version_number')
            ->all();
        $existingLookup = array_fill_keys($existingNumbers, true);

        $upserted = 0;

        foreach ($releases as $release) {
            $tagName = trim((string) ($release['tag_name'] ?? ''));
            if ($tagName === '') {
                continue;
            }

            if (isset($existingLookup[$tagName])) {
                continue;
            }

            $externalId = 'release:' . $tagName;
            $existing = $this->findSuggestion($repository, VcsImportSuggestionKind::Version, $externalId);

            if ($existing !== null && $existing->status !== VcsImportSuggestionStatus::Pending) {
                continue;
            }

            $title = isset($release['name']) && is_string($release['name']) && $release['name'] !== ''
                ? $release['name']
                : $tagName;

            $payload = [
                'title' => $title,
                'summary' => isset($release['body']) && is_string($release['body'])
                    ? mb_substr($release['body'], 0, 2000)
                    : null,
                'tag_name' => $tagName,
                'version_number' => $tagName,
                'published_at' => $release['published_at'] ?? null,
                'html_url' => $release['html_url'] ?? null,
                'body' => isset($release['body']) && is_string($release['body'])
                    ? mb_substr($release['body'], 0, 5000)
                    : null,
            ];

            if ($existing !== null) {
                $existing->update(['payload' => $payload]);
            } else {
                VcsImportSuggestion::query()->create([
                    'product_id' => $repository->product_id,
                    'repository_id' => $repository->id,
                    'kind' => VcsImportSuggestionKind::Version,
                    'external_id' => $externalId,
                    'payload' => $payload,
                    'status' => VcsImportSuggestionStatus::Pending,
                ]);
            }

            $upserted++;
        }

        return $upserted;
    }

    /**
     * @param  list<array{
     *     external_id: string,
     *     number: int|null,
     *     ghsa_id: string|null,
     *     cve_id: string|null,
     *     summary: string,
     *     severity: string|null,
     *     package_name: string|null,
     *     package_ecosystem: string|null,
     *     html_url: string|null,
     *     created_at: string|null
     * }>  $alerts
     */
    private function upsertVulnerabilitySuggestions(ProductRepository $repository, array $alerts): int
    {
        $productId = $repository->product_id;
        $existingCves = ProductVulnerability::query()
            ->where('product_id', $productId)
            ->whereNotNull('cve_id')
            ->pluck('cve_id')
            ->filter()
            ->map(fn($cve) => strtoupper((string) $cve))
            ->all();
        $cveLookup = array_fill_keys($existingCves, true);

        $existingAdvisoryUrls = ProductVulnerability::query()
            ->where('product_id', $productId)
            ->whereNotNull('advisory_url')
            ->pluck('advisory_url')
            ->filter()
            ->all();

        $upserted = 0;

        foreach ($alerts as $alert) {
            $externalId = trim((string) ($alert['external_id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $cveId = isset($alert['cve_id']) && is_string($alert['cve_id']) ? $alert['cve_id'] : null;
            if ($cveId !== null && isset($cveLookup[strtoupper($cveId)])) {
                continue;
            }

            $ghsaId = isset($alert['ghsa_id']) && is_string($alert['ghsa_id']) ? $alert['ghsa_id'] : null;
            $htmlUrl = isset($alert['html_url']) && is_string($alert['html_url']) ? $alert['html_url'] : null;

            if ($ghsaId !== null && $this->advisoryMentions($existingAdvisoryUrls, $ghsaId)) {
                continue;
            }

            $existing = $this->findSuggestion($repository, VcsImportSuggestionKind::Vulnerability, $externalId);

            if ($existing !== null && $existing->status !== VcsImportSuggestionStatus::Pending) {
                continue;
            }

            $packageName = isset($alert['package_name']) && is_string($alert['package_name'])
                ? $alert['package_name']
                : null;
            $summary = (string) ($alert['summary'] ?? 'Dependabot alert');
            $title = $packageName !== null
                ? $packageName . ': ' . $summary
                : $summary;

            $payload = [
                'title' => mb_substr($title, 0, 255),
                'summary' => $summary,
                'cve_id' => $cveId,
                'ghsa_id' => $ghsaId,
                'severity' => $alert['severity'] ?? null,
                'package_name' => $packageName,
                'package_ecosystem' => $alert['package_ecosystem'] ?? null,
                'html_url' => $htmlUrl,
                'created_at' => $alert['created_at'] ?? null,
                'number' => $alert['number'] ?? null,
            ];

            if ($existing !== null) {
                $existing->update(['payload' => $payload]);
            } else {
                VcsImportSuggestion::query()->create([
                    'product_id' => $repository->product_id,
                    'repository_id' => $repository->id,
                    'kind' => VcsImportSuggestionKind::Vulnerability,
                    'external_id' => $externalId,
                    'payload' => $payload,
                    'status' => VcsImportSuggestionStatus::Pending,
                ]);
            }

            $upserted++;
        }

        return $upserted;
    }

    private function findSuggestion(
        ProductRepository $repository,
        VcsImportSuggestionKind $kind,
        string $externalId,
    ): ?VcsImportSuggestion {
        return VcsImportSuggestion::query()
            ->where('repository_id', $repository->id)
            ->where('kind', $kind)
            ->where('external_id', $externalId)
            ->first();
    }

    private function pendingCount(ProductRepository $repository, VcsImportSuggestionKind $kind): int
    {
        return VcsImportSuggestion::query()
            ->where('repository_id', $repository->id)
            ->where('kind', $kind)
            ->where('status', VcsImportSuggestionStatus::Pending)
            ->count();
    }

    /**
     * @param  list<string>  $advisoryUrls
     */
    private function advisoryMentions(array $advisoryUrls, string $needle): bool
    {
        foreach ($advisoryUrls as $url) {
            if (str_contains($url, $needle)) {
                return true;
            }
        }

        return false;
    }
}

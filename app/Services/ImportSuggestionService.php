<?php

namespace App\Services;

use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\ImportSuggestion;
use App\Models\ProductIntegrationLink;
use App\Models\ProductVulnerability;
use App\Models\Task;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportSuggestionService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ProductVulnerabilityService $vulnerabilities,
    ) {
    }

    /**
     * @param  list<array{
     *     external_id: string,
     *     key: string,
     *     title: string,
     *     summary: string|null,
     *     issue_type: string|null,
     *     priority: string|null,
     *     status: string|null,
     *     html_url: string,
     *     updated_at: string|null
     * }>  $issues
     * @return array{task_suggestions_upserted: int, pending_task_suggestions: int}
     */
    public function upsertTaskSuggestionsFromAlm(ProductIntegrationLink $link, array $issues): array
    {
        $link->loadMissing('product');
        $upserted = 0;

        foreach ($issues as $issue) {
            $externalId = trim((string) ($issue['external_id'] ?? ''));
            $key = trim((string) ($issue['key'] ?? ''));

            if ($externalId === '' || $key === '') {
                continue;
            }

            $existing = $this->findSuggestion($link, ImportSuggestionKind::Task, $externalId);

            if ($existing !== null && $existing->status !== ImportSuggestionStatus::Pending) {
                continue;
            }

            $title = trim((string) ($issue['title'] ?? $key));
            if ($title === '') {
                $title = $key;
            }

            $payload = [
                'title' => mb_substr($key . ': ' . $title, 0, 255),
                'summary' => $issue['summary'] ?? null,
                'issue_key' => $key,
                'issue_type' => $issue['issue_type'] ?? null,
                'priority' => $issue['priority'] ?? null,
                'status' => $issue['status'] ?? null,
                'html_url' => $issue['html_url'] ?? null,
                'updated_at' => $issue['updated_at'] ?? null,
            ];

            if ($existing !== null) {
                $existing->update([
                    'title' => $payload['title'],
                    'payload' => $payload,
                ]);
            } else {
                ImportSuggestion::query()->create([
                    'product_id' => $link->product_id,
                    'link_id' => $link->id,
                    'kind' => ImportSuggestionKind::Task,
                    'external_id' => $externalId,
                    'title' => $payload['title'],
                    'payload' => $payload,
                    'status' => ImportSuggestionStatus::Pending,
                ]);
            }

            $upserted++;
        }

        return [
            'task_suggestions_upserted' => $upserted,
            'pending_task_suggestions' => $this->pendingCount($link, ImportSuggestionKind::Task),
        ];
    }

    /**
     * @param  list<array{
     *     external_id: string,
     *     title: string,
     *     summary: string|null,
     *     cve_id: string|null,
     *     severity: string|null,
     *     package_name: string|null,
     *     package_ecosystem: string|null,
     *     html_url: string|null,
     *     snyk_issue_key: string|null,
     *     created_at: string|null,
     *     cvss_score: string|null
     * }>  $findings
     * @return array{vulnerability_suggestions_upserted: int, pending_vulnerability_suggestions: int}
     */
    public function upsertVulnerabilitySuggestionsFromScanner(ProductIntegrationLink $link, array $findings): array
    {
        $link->loadMissing('product');
        $upserted = 0;

        foreach ($findings as $finding) {
            $externalId = trim((string) ($finding['external_id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $existing = $this->findSuggestion($link, ImportSuggestionKind::Vulnerability, $externalId);

            if ($existing !== null && $existing->status !== ImportSuggestionStatus::Pending) {
                continue;
            }

            $cveId = isset($finding['cve_id']) && is_string($finding['cve_id'])
                ? trim($finding['cve_id'])
                : null;
            if ($cveId === '') {
                $cveId = null;
            }

            if ($cveId !== null && $this->productHasCve($link->product_id, $cveId)) {
                continue;
            }

            $title = trim((string) ($finding['title'] ?? $externalId));
            $packageName = isset($finding['package_name']) && is_string($finding['package_name'])
                ? $finding['package_name']
                : null;

            if ($packageName !== null && $packageName !== '' && !str_contains($title, $packageName)) {
                $title = mb_substr($packageName . ': ' . $title, 0, 255);
            } else {
                $title = mb_substr($title !== '' ? $title : $externalId, 0, 255);
            }

            $payload = [
                'title' => $title,
                'summary' => $finding['summary'] ?? null,
                'cve_id' => $cveId,
                'severity' => $finding['severity'] ?? null,
                'package_name' => $packageName,
                'package_ecosystem' => $finding['package_ecosystem'] ?? null,
                'html_url' => $finding['html_url'] ?? null,
                'snyk_issue_key' => $finding['snyk_issue_key'] ?? null,
                'created_at' => $finding['created_at'] ?? null,
                'cvss_score' => $finding['cvss_score'] ?? null,
            ];

            if ($existing !== null) {
                $existing->update([
                    'title' => $payload['title'],
                    'payload' => $payload,
                ]);
            } else {
                ImportSuggestion::query()->create([
                    'product_id' => $link->product_id,
                    'link_id' => $link->id,
                    'kind' => ImportSuggestionKind::Vulnerability,
                    'external_id' => $externalId,
                    'title' => $payload['title'],
                    'payload' => $payload,
                    'status' => ImportSuggestionStatus::Pending,
                ]);
            }

            $upserted++;
        }

        return [
            'vulnerability_suggestions_upserted' => $upserted,
            'pending_vulnerability_suggestions' => $this->pendingCount(
                $link,
                ImportSuggestionKind::Vulnerability,
            ),
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
     *     issue_key: string|null,
     *     issue_type: string|null,
     *     priority: string|null,
     *     status: string|null,
     *     severity: string|null,
     *     cve_id: string|null,
     *     package_name: string|null
     * }>
     */
    public function pendingPayloadForProduct(int $productId): array
    {
        return ImportSuggestion::query()
            ->where('product_id', $productId)
            ->where('status', ImportSuggestionStatus::Pending)
            ->orderByDesc('id')
            ->get()
            ->map(function (ImportSuggestion $suggestion): array {
                $payload = is_array($suggestion->payload) ? $suggestion->payload : [];

                return [
                    'id' => $suggestion->id,
                    'kind' => $suggestion->kind->value,
                    'external_id' => $suggestion->external_id,
                    'title' => $suggestion->title !== ''
                        ? $suggestion->title
                        : (string) ($payload['title'] ?? $suggestion->external_id),
                    'summary' => isset($payload['summary']) && is_string($payload['summary'])
                        ? $payload['summary']
                        : null,
                    'html_url' => isset($payload['html_url']) && is_string($payload['html_url'])
                        ? $payload['html_url']
                        : null,
                    'issue_key' => isset($payload['issue_key']) && is_string($payload['issue_key'])
                        ? $payload['issue_key']
                        : (isset($payload['snyk_issue_key']) && is_string($payload['snyk_issue_key'])
                            ? $payload['snyk_issue_key']
                            : null),
                    'issue_type' => isset($payload['issue_type']) && is_string($payload['issue_type'])
                        ? $payload['issue_type']
                        : null,
                    'priority' => isset($payload['priority']) && is_string($payload['priority'])
                        ? $payload['priority']
                        : null,
                    'status' => isset($payload['status']) && is_string($payload['status'])
                        ? $payload['status']
                        : null,
                    'severity' => isset($payload['severity']) && is_string($payload['severity'])
                        ? $payload['severity']
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

    public function accept(ImportSuggestion $suggestion, User $actor): Task|ProductVulnerability
    {
        if ($suggestion->status !== ImportSuggestionStatus::Pending) {
            throw ValidationException::withMessages([
                'suggestion' => [Translations::get('products.integrations.suggestion_not_pending')],
            ]);
        }

        $suggestion->loadMissing('product');

        $entity = match ($suggestion->kind) {
            ImportSuggestionKind::Task => $this->acceptTask($suggestion, $actor),
            ImportSuggestionKind::Vulnerability => $this->acceptVulnerability($suggestion),
            default => throw new RuntimeException(
                Translations::get('products.integrations.unsupported_suggestion_kind'),
            ),
        };

        $suggestion->update([
            'status' => ImportSuggestionStatus::Accepted,
            'accepted_entity_type' => $entity::class,
            'accepted_entity_id' => $entity->id,
        ]);

        AuditLogger::logImportSuggestionAccepted($suggestion->fresh(), $actor);

        return $entity;
    }

    public function dismiss(ImportSuggestion $suggestion, User $actor): void
    {
        if ($suggestion->status !== ImportSuggestionStatus::Pending) {
            throw ValidationException::withMessages([
                'suggestion' => [Translations::get('products.integrations.suggestion_not_pending')],
            ]);
        }

        $suggestion->update([
            'status' => ImportSuggestionStatus::Dismissed,
        ]);

        AuditLogger::logImportSuggestionDismissed($suggestion->fresh(), $actor);
    }

    private function acceptTask(ImportSuggestion $suggestion, User $actor): Task
    {
        $payload = is_array($suggestion->payload) ? $suggestion->payload : [];
        $issueKey = isset($payload['issue_key']) && is_string($payload['issue_key'])
            ? $payload['issue_key']
            : $suggestion->external_id;
        $htmlUrl = isset($payload['html_url']) && is_string($payload['html_url'])
            ? $payload['html_url']
            : null;
        $summary = isset($payload['summary']) && is_string($payload['summary'])
            ? $payload['summary']
            : null;

        $descriptionLines = [
            'Imported from Jira issue ' . $issueKey . '.',
        ];

        if ($htmlUrl !== null) {
            $descriptionLines[] = 'URL: ' . $htmlUrl;
        }

        if ($summary !== null && $summary !== '') {
            $descriptionLines[] = '';
            $descriptionLines[] = $summary;
        }

        return $this->tasks->create(
            product: $suggestion->product,
            attributes: [
                'title' => mb_substr($suggestion->title !== '' ? $suggestion->title : $issueKey, 0, 255),
                'description' => implode("\n", $descriptionLines),
                'status' => TaskStatus::Open,
                'priority' => $this->mapPriority($payload['priority'] ?? null),
                'assignee_user_id' => null,
                'due_at' => null,
                'subject_type' => null,
                'subject_id' => null,
            ],
            creator: $actor,
        );
    }

    private function acceptVulnerability(ImportSuggestion $suggestion): ProductVulnerability
    {
        $payload = is_array($suggestion->payload) ? $suggestion->payload : [];
        $title = trim((string) ($payload['title'] ?? $suggestion->title ?: 'Snyk finding'));
        $summary = isset($payload['summary']) && is_string($payload['summary'])
            ? $payload['summary']
            : null;
        $cveId = isset($payload['cve_id']) && is_string($payload['cve_id'])
            ? $payload['cve_id']
            : null;
        $advisoryUrl = isset($payload['html_url']) && is_string($payload['html_url'])
            ? $payload['html_url']
            : null;

        $discoveredAt = null;
        if (isset($payload['created_at']) && is_string($payload['created_at']) && $payload['created_at'] !== '') {
            $discoveredAt = Carbon::parse($payload['created_at']);
        }

        $notes = [];
        if (isset($payload['snyk_issue_key']) && is_string($payload['snyk_issue_key'])) {
            $notes[] = 'Snyk: ' . $payload['snyk_issue_key'];
        }
        if (isset($payload['package_name']) && is_string($payload['package_name'])) {
            $notes[] = 'Package: ' . $payload['package_name'];
        }
        if (isset($payload['package_ecosystem']) && is_string($payload['package_ecosystem'])) {
            $notes[] = 'Ecosystem: ' . $payload['package_ecosystem'];
        }
        $notes[] = 'Imported from integration suggestion #' . $suggestion->id;

        $cvss = null;
        if (isset($payload['cvss_score']) && is_numeric($payload['cvss_score'])) {
            $cvss = (string) $payload['cvss_score'];
        }

        return $this->vulnerabilities->create(
            product: $suggestion->product,
            attributes: [
                'title' => mb_substr($title !== '' ? $title : 'Snyk finding', 0, 255),
                'summary' => $summary,
                'cve_id' => $cveId,
                'advisory_url' => $advisoryUrl,
                'discovery_source' => VulnerabilityDiscoverySource::DependencyScanner,
                'discovered_at' => $discoveredAt,
                'awareness_at' => null,
                'status' => VulnerabilityStatus::Reported,
                'cvss_score' => $cvss,
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

    private function mapPriority(mixed $priority): TaskPriority
    {
        if (!is_string($priority) || $priority === '') {
            return TaskPriority::Medium;
        }

        $normalized = strtolower($priority);

        return match (true) {
            str_contains($normalized, 'highest'),
            str_contains($normalized, 'critical'),
            str_contains($normalized, 'blocker'),
            $normalized === 'high' => TaskPriority::High,
            str_contains($normalized, 'lowest'),
            str_contains($normalized, 'trivial'),
            $normalized === 'low' => TaskPriority::Low,
            default => TaskPriority::Medium,
        };
    }

    private function mapSeverity(mixed $severity): VulnerabilityBusinessSeverity
    {
        if (!is_string($severity) || $severity === '') {
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

    private function productHasCve(int $productId, string $cveId): bool
    {
        return ProductVulnerability::query()
            ->where('product_id', $productId)
            ->where('cve_id', $cveId)
            ->exists();
    }

    private function findSuggestion(
        ProductIntegrationLink $link,
        ImportSuggestionKind $kind,
        string $externalId,
    ): ?ImportSuggestion {
        return ImportSuggestion::query()
            ->where('link_id', $link->id)
            ->where('kind', $kind)
            ->where('external_id', $externalId)
            ->first();
    }

    private function pendingCount(ProductIntegrationLink $link, ImportSuggestionKind $kind): int
    {
        return ImportSuggestion::query()
            ->where('link_id', $link->id)
            ->where('kind', $kind)
            ->where('status', ImportSuggestionStatus::Pending)
            ->count();
    }
}

<?php

namespace App\Services;

use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ImportSuggestion;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\Task;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportSuggestionService
{
    public function __construct(
        private readonly TaskService $tasks,
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
     *     status: string|null
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
                        : null,
                    'issue_type' => isset($payload['issue_type']) && is_string($payload['issue_type'])
                        ? $payload['issue_type']
                        : null,
                    'priority' => isset($payload['priority']) && is_string($payload['priority'])
                        ? $payload['priority']
                        : null,
                    'status' => isset($payload['status']) && is_string($payload['status'])
                        ? $payload['status']
                        : null,
                ];
            })
            ->all();
    }

    public function accept(ImportSuggestion $suggestion, User $actor): Task
    {
        if ($suggestion->status !== ImportSuggestionStatus::Pending) {
            throw ValidationException::withMessages([
                'suggestion' => ['Suggestion is not pending.'],
            ]);
        }

        $suggestion->loadMissing('product');

        $entity = match ($suggestion->kind) {
            ImportSuggestionKind::Task => $this->acceptTask($suggestion, $actor),
            default => throw new RuntimeException('Unsupported import suggestion kind.'),
        };

        $suggestion->update([
            'status' => ImportSuggestionStatus::Accepted,
            'accepted_entity_type' => Task::class,
            'accepted_entity_id' => $entity->id,
        ]);

        AuditLogger::logImportSuggestionAccepted($suggestion->fresh(), $actor);

        return $entity;
    }

    public function dismiss(ImportSuggestion $suggestion, User $actor): void
    {
        if ($suggestion->status !== ImportSuggestionStatus::Pending) {
            throw ValidationException::withMessages([
                'suggestion' => ['Suggestion is not pending.'],
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

<?php

namespace App\Services;

use App\Enums\AiAnalysisJobStatus;
use App\Enums\AiAnalysisJobType;
use App\Enums\AiConversationContextType;
use App\Enums\AiDraftType;
use App\Enums\AiMessageRole;
use App\Jobs\IndexAiEmbeddingsJob;
use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysisJob;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PatchCampaign;
use App\Models\Product;
use App\Models\ProductVulnerability;
use App\Models\User;
use App\Support\Translations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AiQueuedAnalysisService
{
    public function __construct(
        private readonly AiAssistantService $assistant,
        private readonly AiEmbeddingIndexer $indexer,
    ) {
    }

    public function queueEnabled(): bool
    {
        return (bool) config('ai.queue.enabled', true);
    }

    /**
     * @return array{conversation: AiConversation, analysis_job: AiAnalysisJob|null}
     */
    public function queueAnalyseDocument(
        Product $product,
        User $user,
        UploadedFile $file,
        ?string $note = null,
    ): array {
        if (!$this->queueEnabled()) {
            $result = $this->assistant->analyseDocument($product, $user, $file, $note);

            return [
                'conversation' => $result['conversation'],
                'analysis_job' => null,
            ];
        }

        $this->assistant->assertEnabled();

        $filename = (string) $file->getClientOriginalName();
        $storedPath = $file->store('ai-uploads/' . now()->format('Y/m/d'), 'local');
        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => Translations::get('assistant.analyse.empty_file'),
            ]);
        }

        [$conversation, $analysisJob] = DB::transaction(function () use ($product, $user, $filename, $storedPath, $note): array {
            $conversation = $this->assistant->startConversation(
                $product,
                $user,
                AiConversationContextType::DocumentAnalyser,
            );

            AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::User,
                'content' => "Analyse uploaded document: {$filename}"
                    . (filled($note) ? "\nNote: " . trim((string) $note) : '')
                    . "\n\n(Queued — waiting for worker)",
                'metadata' => [
                    'filename' => $filename,
                    'mode' => 'document_analyse',
                    'queued' => true,
                ],
            ]);

            $analysisJob = AiAnalysisJob::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'type' => AiAnalysisJobType::DocumentAnalyse,
                'status' => AiAnalysisJobStatus::Pending,
                'payload' => [
                    'stored_path' => $storedPath,
                    'filename' => $filename,
                    'note' => $note,
                ],
            ]);

            return [$conversation, $analysisJob];
        });

        RunAiAnalysisJob::dispatch($analysisJob->id);

        return [
            'conversation' => $conversation->fresh(['messages']),
            'analysis_job' => $analysisJob,
        ];
    }

    /**
     * @return array{conversation: AiConversation, analysis_job: AiAnalysisJob|null}
     */
    public function queueGenerateDraft(
        Product $product,
        User $user,
        PatchCampaign $campaign,
        AiDraftType $draftType,
        ?string $note = null,
    ): array {
        if (!$this->queueEnabled()) {
            $result = $this->assistant->generateDraft($product, $user, $campaign, $draftType, $note);

            return [
                'conversation' => $result['conversation'],
                'analysis_job' => null,
            ];
        }

        $this->assistant->assertEnabled();

        if ($campaign->product_id !== $product->id) {
            abort(404);
        }

        [$conversation, $analysisJob] = DB::transaction(function () use ($product, $user, $campaign, $draftType, $note): array {
            $conversation = $this->assistant->startConversation(
                $product,
                $user,
                AiConversationContextType::Draft,
            );

            AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::User,
                'content' => "Generate {$draftType->value} draft for campaign #{$campaign->id}"
                    . (filled($note) ? "\nNote: " . trim((string) $note) : '')
                    . "\n\n(Queued — waiting for worker)",
                'metadata' => [
                    'mode' => 'draft_generate',
                    'campaign_id' => $campaign->id,
                    'draft_type' => $draftType->value,
                    'queued' => true,
                ],
            ]);

            $analysisJob = AiAnalysisJob::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'type' => AiAnalysisJobType::DraftGenerate,
                'status' => AiAnalysisJobStatus::Pending,
                'payload' => [
                    'campaign_id' => $campaign->id,
                    'draft_type' => $draftType->value,
                    'note' => $note,
                ],
            ]);

            return [$conversation, $analysisJob];
        });

        RunAiAnalysisJob::dispatch($analysisJob->id);

        return [
            'conversation' => $conversation->fresh(['messages']),
            'analysis_job' => $analysisJob,
        ];
    }

    /**
     * @return array{conversation: AiConversation, analysis_job: AiAnalysisJob|null}
     */
    public function queueTriageVulnerability(
        Product $product,
        User $user,
        ProductVulnerability $vulnerability,
        ?string $note = null,
    ): array {
        if (!$this->queueEnabled()) {
            $result = $this->assistant->triageVulnerability($product, $user, $vulnerability, $note);

            return [
                'conversation' => $result['conversation'],
                'analysis_job' => null,
            ];
        }

        $this->assistant->assertEnabled();

        if ($vulnerability->product_id !== $product->id) {
            abort(404);
        }

        [$conversation, $analysisJob] = DB::transaction(function () use ($product, $user, $vulnerability, $note): array {
            $conversation = $this->assistant->startConversation(
                $product,
                $user,
                AiConversationContextType::VulnerabilityTriage,
            );

            AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::User,
                'content' => "Triage vulnerability #{$vulnerability->id}: {$vulnerability->title}"
                    . (filled($note) ? "\nNote: " . trim((string) $note) : '')
                    . "\n\n(Queued — waiting for worker)",
                'metadata' => [
                    'mode' => 'vulnerability_triage',
                    'vulnerability_id' => $vulnerability->id,
                    'queued' => true,
                ],
            ]);

            $analysisJob = AiAnalysisJob::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'type' => AiAnalysisJobType::VulnerabilityTriage,
                'status' => AiAnalysisJobStatus::Pending,
                'payload' => [
                    'vulnerability_id' => $vulnerability->id,
                    'note' => $note,
                ],
            ]);

            return [$conversation, $analysisJob];
        });

        RunAiAnalysisJob::dispatch($analysisJob->id);

        return [
            'conversation' => $conversation->fresh(['messages']),
            'analysis_job' => $analysisJob,
        ];
    }

    /**
     * @return array{analysis_job: AiAnalysisJob}
     */
    public function queueRagIndex(Product $product, ?User $user = null): array
    {
        if (!(bool) config('ai.rag.enabled', true)) {
            throw ValidationException::withMessages([
                'assistant' => 'RAG indexing is disabled.',
            ]);
        }

        $analysisJob = AiAnalysisJob::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'conversation_id' => null,
            'type' => AiAnalysisJobType::RagIndex,
            'status' => AiAnalysisJobStatus::Pending,
            'payload' => [],
        ]);

        IndexAiEmbeddingsJob::dispatch($analysisJob->id);

        return ['analysis_job' => $analysisJob];
    }

    public function process(AiAnalysisJob $job): void
    {
        if ($job->status === AiAnalysisJobStatus::Succeeded) {
            return;
        }

        $job->update([
            'status' => AiAnalysisJobStatus::Running,
            'started_at' => $job->started_at ?? now(),
            'error_message' => null,
        ]);

        try {
            match ($job->type) {
                AiAnalysisJobType::DocumentAnalyse => $this->processDocumentAnalyse($job),
                AiAnalysisJobType::DraftGenerate => $this->processDraftGenerate($job),
                AiAnalysisJobType::VulnerabilityTriage => $this->processVulnerabilityTriage($job),
                AiAnalysisJobType::RagIndex => $this->processRagIndex($job),
            };

            $job->update([
                'status' => AiAnalysisJobStatus::Succeeded,
                'finished_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            $this->appendFailureAssistantMessage($job, $e->getMessage());

            $job->update([
                'status' => AiAnalysisJobStatus::Failed,
                'finished_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
        }
    }

    /**
     * @return array{id: int, type: string, status: string, error_message: string|null}|null
     */
    public function latestJobPayloadForConversation(?AiConversation $conversation): ?array
    {
        if ($conversation === null) {
            return null;
        }

        $job = AiAnalysisJob::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        if ($job === null) {
            return null;
        }

        return [
            'id' => $job->id,
            'type' => $job->type->value,
            'status' => $job->status->value,
            'error_message' => $job->error_message,
        ];
    }

    private function processDocumentAnalyse(AiAnalysisJob $job): void
    {
        $payload = $job->payload ?? [];
        $storedPath = (string) ($payload['stored_path'] ?? '');
        $filename = (string) ($payload['filename'] ?? 'document.txt');
        $note = isset($payload['note']) ? (is_string($payload['note']) ? $payload['note'] : null) : null;

        $absolute = Storage::disk('local')->path($storedPath);
        if ($storedPath === '' || !is_file($absolute)) {
            throw new \RuntimeException('Queued analysis file is missing.');
        }

        $uploaded = new UploadedFile(
            $absolute,
            $filename,
            null,
            null,
            true,
        );

        $product = $job->product()->firstOrFail();
        $user = $job->user()->firstOrFail();
        $conversation = $job->conversation()->firstOrFail();

        $this->assistant->analyseDocument(
            $product,
            $user,
            $uploaded,
            $note,
            $conversation,
        );

        Storage::disk('local')->delete($storedPath);
    }

    private function processDraftGenerate(AiAnalysisJob $job): void
    {
        $payload = $job->payload ?? [];
        $campaign = PatchCampaign::query()->findOrFail((int) ($payload['campaign_id'] ?? 0));
        $draftType = AiDraftType::from((string) ($payload['draft_type'] ?? AiDraftType::CustomerNotification->value));
        $note = isset($payload['note']) ? (is_string($payload['note']) ? $payload['note'] : null) : null;

        $this->assistant->generateDraft(
            $job->product()->firstOrFail(),
            $job->user()->firstOrFail(),
            $campaign,
            $draftType,
            $note,
            $job->conversation()->firstOrFail(),
        );
    }

    private function processVulnerabilityTriage(AiAnalysisJob $job): void
    {
        $payload = $job->payload ?? [];
        $vulnerability = ProductVulnerability::query()->findOrFail((int) ($payload['vulnerability_id'] ?? 0));
        $note = isset($payload['note']) ? (is_string($payload['note']) ? $payload['note'] : null) : null;

        $this->assistant->triageVulnerability(
            $job->product()->firstOrFail(),
            $job->user()->firstOrFail(),
            $vulnerability,
            $note,
            $job->conversation()->firstOrFail(),
        );
    }

    private function processRagIndex(AiAnalysisJob $job): void
    {
        $this->indexer->indexProduct($job->product()->firstOrFail());
    }

    private function appendFailureAssistantMessage(AiAnalysisJob $job, string $message): void
    {
        if ($job->conversation_id === null) {
            return;
        }

        $hasAssistant = AiMessage::query()
            ->where('conversation_id', $job->conversation_id)
            ->where('role', AiMessageRole::Assistant)
            ->exists();

        if ($hasAssistant) {
            return;
        }

        AiMessage::query()->create([
            'conversation_id' => $job->conversation_id,
            'role' => AiMessageRole::Assistant,
            'content' => 'Queued analysis failed. Please retry. Human review is still required for all compliance decisions.',
            'metadata' => [
                'mode' => $job->type->value,
                'queued' => true,
                'failed' => true,
                'error' => mb_substr($message, 0, 500),
            ],
        ]);
    }
}

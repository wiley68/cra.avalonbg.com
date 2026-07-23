<?php

namespace App\Services;

use App\Contracts\AiProvider;
use App\Contracts\EmbeddingProvider;
use App\Enums\AiConversationContextType;
use App\Enums\AiDraftType;
use App\Enums\AiMessageRole;
use App\Enums\AiProviderDriver;
use App\Enums\EmbeddingProviderDriver;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PatchCampaign;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Models\ProductVulnerability;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Services\Ai\AiDocumentAnalysePrompt;
use App\Services\Ai\AiDocumentTextExtractor;
use App\Services\Ai\AiDraftParser;
use App\Services\Ai\AiDraftPrompt;
use App\Services\Ai\AiSuggestionsParser;
use App\Services\Ai\AiUsiSectionDraftParser;
use App\Services\Ai\AiUsiSectionDraftPrompt;
use App\Services\Ai\AiVulnerabilityTriageParser;
use App\Services\Ai\AiVulnerabilityTriagePrompt;
use App\Services\Ai\AnthropicAiProvider;
use App\Services\Ai\OpenAiEmbeddingProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\StubAiProvider;
use App\Services\Ai\StubEmbeddingProvider;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiAssistantService
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiContextBuilder $contextBuilder,
        private readonly AiCampaignContextBuilder $campaignContextBuilder,
        private readonly AiVulnerabilityContextBuilder $vulnerabilityContextBuilder,
        private readonly AiDocumentTextExtractor $documentTextExtractor,
        private readonly AiRagRetriever $ragRetriever,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('ai.enabled');
    }

    public function driver(): AiProviderDriver
    {
        $value = (string) config('ai.provider', AiProviderDriver::Stub->value);

        return AiProviderDriver::tryFrom($value) ?? AiProviderDriver::Stub;
    }

    /**
     * Resolve the concrete provider for the configured driver.
     * Used by the container binding; call sites should inject AiProvider.
     */
    public static function makeProvider(?string $driver = null): AiProvider
    {
        $resolved = AiProviderDriver::tryFrom(
            $driver ?? (string) config('ai.provider', AiProviderDriver::Stub->value),
        ) ?? AiProviderDriver::Stub;

        return match ($resolved) {
            AiProviderDriver::Stub => new StubAiProvider,
            AiProviderDriver::OpenAi => new OpenAiProvider,
            AiProviderDriver::Anthropic => new AnthropicAiProvider,
        };
    }

    public static function makeEmbeddingProvider(?string $driver = null): EmbeddingProvider
    {
        $resolved = EmbeddingProviderDriver::tryFrom(
            $driver ?? (string) config('ai.embeddings.provider', EmbeddingProviderDriver::Stub->value),
        ) ?? EmbeddingProviderDriver::Stub;

        return match ($resolved) {
            EmbeddingProviderDriver::Stub => new StubEmbeddingProvider,
            EmbeddingProviderDriver::OpenAi => new OpenAiEmbeddingProvider,
        };
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{context?: string|null}  $options
     * @return array{content: string, provider: string, model: string|null}
     */
    public function complete(array $messages, array $options = []): array
    {
        $this->assertEnabled();

        return $this->provider->complete($messages, $options);
    }

    public function startConversation(
        Product $product,
        User $user,
        AiConversationContextType $contextType = AiConversationContextType::Chat,
    ): AiConversation {
        $this->assertEnabled();

        return AiConversation::query()->create([
            'organization_id' => $product->organization_id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'context_type' => $contextType,
        ]);
    }

    /**
     * Append a user turn, call the provider, persist the assistant reply (append-only).
     *
     * @param  array{context?: string|null}  $options
     * @return array{
     *     conversation: AiConversation,
     *     user_message: AiMessage,
     *     assistant_message: AiMessage
     * }
     */
    public function sendMessage(
        AiConversation $conversation,
        User $user,
        string $content,
        array $options = [],
    ): array {
        $this->assertEnabled();
        $this->assertConversationOwner($conversation, $user);

        $trimmed = trim($content);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'content' => Translations::get('assistant.message_required'),
            ]);
        }

        return DB::transaction(function () use ($conversation, $user, $trimmed, $options): array {
            $userMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::User,
                'content' => $trimmed,
                'metadata' => null,
            ]);

            $history = $conversation->messages()
                ->orderBy('id')
                ->get()
                ->map(fn(AiMessage $message): array => [
                    'role' => $message->role->value,
                    'content' => $message->content,
                ])
                ->all();

            $grounded = $this->resolveContext($conversation, $options, $trimmed);
            $context = $grounded['context'];
            $ragHits = $grounded['rag_hits'];
            $hasContext = $context !== null && $context !== '';
            $contextChars = $context !== null ? mb_strlen($context) : 0;

            $completion = $this->provider->complete($history, [
                ...$options,
                'context' => $context,
            ]);

            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $completion['content'],
                'metadata' => [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'has_context' => $hasContext,
                    'context_chars' => $contextChars,
                    'rag_hits' => $ragHits,
                ],
            ]);

            $conversation->touch();

            AuditLogger::logAiRequestCompleted(
                $conversation,
                $user,
                $userMessage,
                $assistantMessage,
                [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'has_context' => $hasContext,
                    'context_chars' => $contextChars,
                    'rag_hits' => $ragHits,
                ],
            );

            return [
                'conversation' => $conversation->fresh(['messages']),
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ];
        });
    }

    /**
     * One-shot document upload analysis → structured suggestions JSON (human review required).
     *
     * @return array{
     *     conversation: AiConversation,
     *     user_message: AiMessage,
     *     assistant_message: AiMessage,
     *     suggestions: array<string, mixed>|null
     * }
     */
    public function analyseDocument(
        Product $product,
        User $user,
        UploadedFile $file,
        ?string $note = null,
        ?AiConversation $existingConversation = null,
    ): array {
        $this->assertEnabled();

        if ($existingConversation !== null) {
            $this->assertConversationOwner($existingConversation, $user);
            if (
                $existingConversation->product_id !== $product->id
                || $existingConversation->context_type !== AiConversationContextType::DocumentAnalyser
            ) {
                abort(404);
            }
        }

        $filename = (string) $file->getClientOriginalName();
        $documentText = $this->documentTextExtractor->extract($file);
        $requirementHints = $this->requirementHints($product);
        $prompt = AiDocumentAnalysePrompt::userPrompt(
            $filename,
            $documentText,
            $note,
            $requirementHints,
        );

        return DB::transaction(function () use ($product, $user, $filename, $prompt, $note, $documentText, $existingConversation): array {
            $conversation = $existingConversation ?? $this->startConversation(
                $product,
                $user,
                AiConversationContextType::DocumentAnalyser,
            );

            $userMessage = $existingConversation !== null
                ? AiMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('role', AiMessageRole::User)
                    ->orderBy('id')
                    ->firstOrFail()
                : AiMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::User,
                    'content' => "Analyse uploaded document: {$filename}"
                        . (filled($note) ? "\nNote: " . trim((string) $note) : ''),
                    'metadata' => [
                        'filename' => $filename,
                        'document_chars' => mb_strlen($documentText),
                        'mode' => 'document_analyse',
                    ],
                ]);

            if ($existingConversation !== null) {
                $userMessage->update([
                    'metadata' => array_merge(
                        is_array($userMessage->metadata) ? $userMessage->metadata : [],
                        [
                            'document_chars' => mb_strlen($documentText),
                            'queued' => false,
                        ],
                    ),
                ]);
            }

            $context = $this->contextBuilder->forProduct($product);
            $completion = $this->provider->complete([
                ['role' => 'user', 'content' => $prompt],
            ], [
                'context' => $context,
                'mode' => 'document_analyse',
                'filename' => $filename,
            ]);

            $suggestions = AiSuggestionsParser::parse($completion['content']);
            $readable = $suggestions !== null
                ? AiSuggestionsParser::toReadableSummary($suggestions, $filename)
                : trim($completion['content']);

            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $readable !== '' ? $readable : $completion['content'],
                'metadata' => [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'has_context' => $context !== '',
                    'context_chars' => mb_strlen($context),
                    'mode' => 'document_analyse',
                    'filename' => $filename,
                    'suggestions' => $suggestions,
                    'suggestions_parsed' => $suggestions !== null,
                ],
            ]);

            $conversation->touch();

            AuditLogger::logAiDocumentAnalysed(
                $conversation,
                $user,
                $userMessage,
                $assistantMessage,
                [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'filename' => $filename,
                    'document_chars' => mb_strlen($documentText),
                    'suggestions_parsed' => $suggestions !== null,
                    'requirement_mappings' => count($suggestions['requirement_mappings'] ?? []),
                    'evidence_mappings' => count($suggestions['evidence_mappings'] ?? []),
                    'gaps' => count($suggestions['gaps'] ?? []),
                ],
            );

            return [
                'conversation' => $conversation->fresh(['messages']),
                'user_message' => $userMessage->fresh(),
                'assistant_message' => $assistantMessage,
                'suggestions' => $suggestions,
            ];
        });
    }

    /**
     * @return array{
     *     conversation: AiConversation,
     *     user_message: AiMessage,
     *     assistant_message: AiMessage,
     *     draft: array<string, mixed>|null
     * }
     */
    public function generateDraft(
        Product $product,
        User $user,
        PatchCampaign $campaign,
        AiDraftType $draftType,
        ?string $note = null,
        ?AiConversation $existingConversation = null,
    ): array {
        $this->assertEnabled();

        if ($campaign->product_id !== $product->id) {
            abort(404);
        }

        if ($existingConversation !== null) {
            $this->assertConversationOwner($existingConversation, $user);
            if (
                $existingConversation->product_id !== $product->id
                || $existingConversation->context_type !== AiConversationContextType::Draft
            ) {
                abort(404);
            }
        }

        $campaignContext = $this->campaignContextBuilder->forCampaign($campaign);
        $prompt = AiDraftPrompt::userPrompt($draftType, $campaignContext, $note);

        return DB::transaction(function () use ($product, $user, $campaign, $draftType, $prompt, $note, $campaignContext, $existingConversation): array {
            $conversation = $existingConversation ?? $this->startConversation(
                $product,
                $user,
                AiConversationContextType::Draft,
            );

            $userMessage = $existingConversation !== null
                ? AiMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('role', AiMessageRole::User)
                    ->orderBy('id')
                    ->firstOrFail()
                : AiMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::User,
                    'content' => "Generate {$draftType->value} draft for campaign #{$campaign->id}"
                        . (filled($note) ? "\nNote: " . trim((string) $note) : ''),
                    'metadata' => [
                        'mode' => 'draft_generate',
                        'campaign_id' => $campaign->id,
                        'draft_type' => $draftType->value,
                    ],
                ]);

            $context = $this->contextBuilder->forProduct($product);
            $completion = $this->provider->complete([
                ['role' => 'user', 'content' => $prompt],
            ], [
                'context' => $context . "\n\n" . $campaignContext,
                'mode' => 'draft_generate',
                'draft_type' => $draftType->value,
                'campaign_id' => $campaign->id,
            ]);

            $draft = AiDraftParser::parse($completion['content'], $draftType);
            if ($draft !== null) {
                $draft['campaign_id'] = $campaign->id;
            }

            $readable = $draft !== null
                ? AiDraftParser::toReadableSummary($draft)
                : trim($completion['content']);

            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $readable !== '' ? $readable : $completion['content'],
                'metadata' => [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'has_context' => $context !== '',
                    'context_chars' => mb_strlen($context),
                    'mode' => 'draft_generate',
                    'campaign_id' => $campaign->id,
                    'draft_type' => $draftType->value,
                    'draft' => $draft,
                    'draft_parsed' => $draft !== null,
                ],
            ]);

            $conversation->touch();

            AuditLogger::logAiDraftGenerated(
                $conversation,
                $user,
                $userMessage,
                $assistantMessage,
                [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'campaign_id' => $campaign->id,
                    'draft_type' => $draftType->value,
                    'draft_parsed' => $draft !== null,
                    'highlights' => count($draft['highlights'] ?? []),
                    'recommended_actions' => count($draft['recommended_actions'] ?? []),
                ],
            );

            return [
                'conversation' => $conversation->fresh(['messages']),
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'draft' => $draft,
            ];
        });
    }

    /**
     * Suggest Markdown for one USI section. Does not write the section body or publish.
     *
     * @return array{
     *     draft: array{
     *         section_key: string,
     *         body_markdown: string,
     *         human_review_required: bool,
     *         disclaimer: string
     *     },
     *     provider: string,
     *     model: string|null
     * }
     */
    public function suggestUsiSectionDraft(
        Product $product,
        UserSecurityInstruction $instruction,
        User $user,
        UserSecurityInstructionSectionKey $sectionKey,
        ?string $currentBody = null,
        ?string $note = null,
    ): array {
        $this->assertEnabled();

        if ($instruction->product_id !== $product->id) {
            abort(404);
        }

        if (!$instruction->isEditable()) {
            throw ValidationException::withMessages([
                'section_key' => Translations::get('products.user_security_instructions.cannot_edit_locked'),
            ]);
        }

        $sectionTitle = Translations::get(
            'products.user_security_instructions.sections.' . $sectionKey->value,
        );
        $context = $this->contextBuilder->forProduct($product);
        $prompt = AiUsiSectionDraftPrompt::userPrompt(
            $sectionKey,
            is_string($sectionTitle) ? $sectionTitle : $sectionKey->value,
            $instruction->locale,
            $context,
            $currentBody,
            $note,
        );

        $completion = $this->provider->complete([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'context' => $context,
            'mode' => 'usi_section_draft',
            'section_key' => $sectionKey->value,
            'section_title' => is_string($sectionTitle) ? $sectionTitle : $sectionKey->value,
            'locale' => $instruction->locale,
            'instruction_id' => $instruction->id,
        ]);

        $draft = AiUsiSectionDraftParser::parse($completion['content'], $sectionKey);
        if ($draft === null) {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_failed'),
            ]);
        }

        AuditLogger::logAiUsiSectionDraftSuggested($instruction, $user, [
            'section_key' => $sectionKey->value,
            'provider' => $completion['provider'],
            'model' => $completion['model'],
            'draft_parsed' => true,
            'locale' => $instruction->locale,
        ]);

        return [
            'draft' => $draft,
            'provider' => $completion['provider'],
            'model' => $completion['model'],
        ];
    }

    /**
     * @return array{
     *     conversation: AiConversation,
     *     user_message: AiMessage,
     *     assistant_message: AiMessage,
     *     suggestions: array<string, mixed>|null
     * }
     */
    public function triageVulnerability(
        Product $product,
        User $user,
        ProductVulnerability $vulnerability,
        ?string $note = null,
        ?AiConversation $existingConversation = null,
    ): array {
        $this->assertEnabled();

        if ($vulnerability->product_id !== $product->id) {
            abort(404);
        }

        if ($existingConversation !== null) {
            $this->assertConversationOwner($existingConversation, $user);
            if (
                $existingConversation->product_id !== $product->id
                || $existingConversation->context_type !== AiConversationContextType::VulnerabilityTriage
            ) {
                abort(404);
            }
        }

        $vulnContext = $this->vulnerabilityContextBuilder->forVulnerability($vulnerability);
        $prompt = AiVulnerabilityTriagePrompt::userPrompt($vulnContext, $note);

        return DB::transaction(function () use ($product, $user, $vulnerability, $prompt, $note, $vulnContext, $existingConversation): array {
            $conversation = $existingConversation ?? $this->startConversation(
                $product,
                $user,
                AiConversationContextType::VulnerabilityTriage,
            );

            $userMessage = $existingConversation !== null
                ? AiMessage::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('role', AiMessageRole::User)
                    ->orderBy('id')
                    ->firstOrFail()
                : AiMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => AiMessageRole::User,
                    'content' => "Triage vulnerability #{$vulnerability->id}: {$vulnerability->title}"
                        . (filled($note) ? "\nNote: " . trim((string) $note) : ''),
                    'metadata' => [
                        'mode' => 'vulnerability_triage',
                        'vulnerability_id' => $vulnerability->id,
                    ],
                ]);

            $context = $this->contextBuilder->forProduct($product);
            $completion = $this->provider->complete([
                ['role' => 'user', 'content' => $prompt],
            ], [
                'context' => $context . "\n\n" . $vulnContext,
                'mode' => 'vulnerability_triage',
                'vulnerability_id' => $vulnerability->id,
            ]);

            $suggestions = AiVulnerabilityTriageParser::parse($completion['content']);
            if ($suggestions !== null) {
                $suggestions['vulnerability_id'] = $vulnerability->id;
            }

            $readable = $suggestions !== null
                ? AiVulnerabilityTriageParser::toReadableSummary($suggestions)
                : trim($completion['content']);

            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $readable !== '' ? $readable : $completion['content'],
                'metadata' => [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'has_context' => $context !== '',
                    'context_chars' => mb_strlen($context),
                    'mode' => 'vulnerability_triage',
                    'vulnerability_id' => $vulnerability->id,
                    'triage' => $suggestions,
                    'triage_parsed' => $suggestions !== null,
                ],
            ]);

            $conversation->touch();

            $versionSuggestions = count($suggestions['suggested_affected_version_ids'] ?? [])
                + count($suggestions['suggested_fixed_version_ids'] ?? []);

            AuditLogger::logAiVulnerabilityTriageSuggested(
                $conversation,
                $user,
                $userMessage,
                $assistantMessage,
                [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                    'vulnerability_id' => $vulnerability->id,
                    'suggestions_parsed' => $suggestions !== null,
                    'suggested_status' => $suggestions['suggested_status'] ?? '',
                    'suggested_business_severity' => $suggestions['suggested_business_severity'] ?? '',
                    'component_suggestions' => count($suggestions['suggested_component_ids'] ?? []),
                    'version_suggestions' => $versionSuggestions,
                    'cross_product_hints' => count($suggestions['cross_product_hints'] ?? []),
                ],
            );

            return [
                'conversation' => $conversation->fresh(['messages']),
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'suggestions' => $suggestions,
            ];
        });
    }

    /**
     * @return list<array{code: string, status: string}>
     */
    private function requirementHints(Product $product): array
    {
        $limit = max(1, (int) config('ai.context_requirements_limit', 40));

        return ProductRequirement::query()
            ->where('product_id', $product->id)
            ->with(['requirement:id,code'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn(ProductRequirement $row): array => [
                'code' => (string) ($row->requirement?->code ?? 'unknown'),
                'status' => $row->status->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{context?: string|null}  $options
     * @return array{context: string|null, rag_hits: int}
     */
    private function resolveContext(AiConversation $conversation, array $options, ?string $query = null): array
    {
        if (array_key_exists('context', $options)) {
            $explicit = $options['context'];

            return [
                'context' => $explicit === null ? null : (string) $explicit,
                'rag_hits' => 0,
            ];
        }

        $conversation->loadMissing('product');
        $product = $conversation->product;

        if ($product === null) {
            return ['context' => null, 'rag_hits' => 0];
        }

        $base = $this->contextBuilder->forProduct($product, truncate: false);
        $ragHits = 0;

        if ($query !== null && $query !== '' && $this->ragRetriever->isEnabled()) {
            $retrieved = $this->ragRetriever->retrieve($product, $query);
            $ragHits = $retrieved['hits'];

            if ($retrieved['text'] !== '') {
                $base = trim($base . "\n\n" . $retrieved['text']);
            }
        }

        $max = max(500, (int) config('ai.context_max_chars', 8000));
        if (mb_strlen($base) > $max) {
            $base = rtrim(mb_substr($base, 0, $max - 14)) . "\n…[truncated]";
        }

        return [
            'context' => $base,
            'rag_hits' => $ragHits,
        ];
    }

    public function latestForProductUser(Product $product, User $user): ?AiConversation
    {
        return AiConversation::query()
            ->where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->where('context_type', AiConversationContextType::Chat)
            ->latest('id')
            ->first();
    }

    public function getOrStartConversation(
        Product $product,
        User $user,
        AiConversationContextType $contextType = AiConversationContextType::Chat,
    ): AiConversation {
        $existing = $this->latestForProductUser($product, $user);

        if ($existing !== null) {
            return $existing;
        }

        return $this->startConversation($product, $user, $contextType);
    }

    /**
     * @return array{
     *     id: int,
     *     context_type: string,
     *     messages: list<array{
     *         id: int,
     *         role: string,
     *         content: string,
     *         created_at: string|null,
     *         metadata: array<string, mixed>|null
     *     }>
     * }|null
     */
    public function conversationPayload(?AiConversation $conversation): ?array
    {
        if ($conversation === null) {
            return null;
        }

        $conversation->loadMissing('messages');

        return [
            'id' => $conversation->id,
            'context_type' => $conversation->context_type->value,
            'messages' => $conversation->messages
                ->map(fn(AiMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'metadata' => $message->metadata,
                ])
                ->values()
                ->all(),
        ];
    }

    public function assertEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.disabled'),
            ]);
        }
    }

    private function assertConversationOwner(AiConversation $conversation, User $user): void
    {
        if ($conversation->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'conversation' => Translations::get('assistant.conversation_forbidden'),
            ]);
        }
    }
}

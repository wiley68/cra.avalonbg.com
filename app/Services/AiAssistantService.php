<?php

namespace App\Services;

use App\Contracts\AiProvider;
use App\Enums\AiConversationContextType;
use App\Enums\AiMessageRole;
use App\Enums\AiProviderDriver;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Models\User;
use App\Services\Ai\AiDocumentAnalysePrompt;
use App\Services\Ai\AiDocumentTextExtractor;
use App\Services\Ai\AiSuggestionsParser;
use App\Services\Ai\AnthropicAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\StubAiProvider;
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
        private readonly AiDocumentTextExtractor $documentTextExtractor,
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

            $context = $this->resolveContext($conversation, $options);
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
    ): array {
        $this->assertEnabled();

        $filename = (string) $file->getClientOriginalName();
        $documentText = $this->documentTextExtractor->extract($file);
        $requirementHints = $this->requirementHints($product);
        $prompt = AiDocumentAnalysePrompt::userPrompt(
            $filename,
            $documentText,
            $note,
            $requirementHints,
        );

        return DB::transaction(function () use ($product, $user, $filename, $prompt, $note, $documentText): array {
            $conversation = $this->startConversation(
                $product,
                $user,
                AiConversationContextType::DocumentAnalyser,
            );

            $userMessage = AiMessage::query()->create([
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
     */
    private function resolveContext(AiConversation $conversation, array $options): ?string
    {
        if (array_key_exists('context', $options)) {
            $explicit = $options['context'];

            return $explicit === null ? null : (string) $explicit;
        }

        $conversation->loadMissing('product');
        $product = $conversation->product;

        if ($product === null) {
            return null;
        }

        return $this->contextBuilder->forProduct($product);
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

    private function assertEnabled(): void
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

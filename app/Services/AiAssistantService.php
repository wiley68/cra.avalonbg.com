<?php

namespace App\Services;

use App\Contracts\AiProvider;
use App\Enums\AiConversationContextType;
use App\Enums\AiMessageRole;
use App\Enums\AiProviderDriver;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\StubAiProvider;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AiAssistantService
{
    public function __construct(
        private readonly AiProvider $provider,
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
            AiProviderDriver::OpenAi,
            AiProviderDriver::Anthropic => throw new InvalidArgumentException(
                "AI provider [{$resolved->value}] is not implemented yet; use stub.",
            ),
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

        return DB::transaction(function () use ($conversation, $trimmed, $options): array {
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

            $completion = $this->provider->complete($history, $options);

            $assistantMessage = AiMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $completion['content'],
                'metadata' => [
                    'provider' => $completion['provider'],
                    'model' => $completion['model'],
                ],
            ]);

            $conversation->touch();

            return [
                'conversation' => $conversation->fresh(['messages']),
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ];
        });
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
     *     messages: list<array{id: int, role: string, content: string, created_at: string|null}>
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

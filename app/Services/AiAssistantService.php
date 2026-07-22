<?php

namespace App\Services;

use App\Contracts\AiProvider;
use App\Enums\AiProviderDriver;
use App\Services\Ai\StubAiProvider;
use App\Support\Translations;
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
        if (!$this->isEnabled()) {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.disabled'),
            ]);
        }

        return $this->provider->complete($messages, $options);
    }
}

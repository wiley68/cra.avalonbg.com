<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Enums\AiProviderDriver;
use App\Support\Translations;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnthropicAiProvider implements AiProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{context?: string|null}  $options
     * @return array{content: string, provider: string, model: string|null}
     */
    public function complete(array $messages, array $options = []): array
    {
        $apiKey = trim((string) config('ai.providers.anthropic.api_key'));
        $model = (string) config('ai.providers.anthropic.model', 'claude-sonnet-4-20250514');
        $baseUrl = rtrim((string) config('ai.providers.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');
        $timeout = max(5, (int) config('ai.providers.anthropic.timeout', 60));
        $version = (string) config('ai.providers.anthropic.version', '2023-06-01');
        $maxTokens = max(256, (int) config('ai.providers.anthropic.max_tokens', 2048));

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_misconfigured'),
            ]);
        }

        $payloadMessages = [];
        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $payloadMessages[] = [
                'role' => $role,
                'content' => (string) ($message['content'] ?? ''),
            ];
        }

        if ($payloadMessages === []) {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.message_required'),
            ]);
        }

        $system = AiSystemPrompt::build($options['context'] ?? null);
        if (($options['mode'] ?? null) === 'document_analyse') {
            $system .= "\n\n" . AiDocumentAnalysePrompt::systemAddon();
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $version,
                ])
                ->acceptJson()
                ->timeout($timeout)
                ->post('/messages', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => $payloadMessages,
                    'temperature' => 0.2,
                ])
                ->throw();
        } catch (RequestException $e) {
            report($e);

            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_failed'),
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_failed'),
            ]);
        }

        $blocks = data_get($response->json(), 'content', []);
        $text = '';
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= (string) ($block['text'] ?? '');
                }
            }
        }

        $content = trim($text);
        if ($content === '') {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_failed'),
            ]);
        }

        $resolvedModel = data_get($response->json(), 'model');

        return [
            'content' => $content,
            'provider' => AiProviderDriver::Anthropic->value,
            'model' => is_string($resolvedModel) && $resolvedModel !== '' ? $resolvedModel : $model,
        ];
    }
}

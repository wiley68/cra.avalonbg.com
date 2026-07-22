<?php

use App\Enums\AiProviderDriver;
use App\Services\Ai\AnthropicAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\AiAssistantService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('makeProvider resolves openai and anthropic adapters', function () {
    expect(AiAssistantService::makeProvider(AiProviderDriver::OpenAi->value))
        ->toBeInstanceOf(OpenAiProvider::class)
        ->and(AiAssistantService::makeProvider(AiProviderDriver::Anthropic->value))
        ->toBeInstanceOf(AnthropicAiProvider::class);
});

test('OpenAiProvider posts chat completions and returns assistant content', function () {
    config([
        'ai.providers.openai.api_key' => 'test-openai-key',
        'ai.providers.openai.model' => 'gpt-4o-mini',
        'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
    ]);

    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-test',
            'model' => 'gpt-4o-mini-2024-07-18',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Grounded OpenAI reply about controls.',
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = (new OpenAiProvider)->complete([
        ['role' => 'user', 'content' => 'Summarise controls'],
    ], [
        'context' => '## Product\nName: Demo',
    ]);

    expect($result['provider'])->toBe(AiProviderDriver::OpenAi->value)
        ->and($result['model'])->toBe('gpt-4o-mini-2024-07-18')
        ->and($result['content'])->toContain('Grounded OpenAI reply');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-openai-key')
            && ($body['model'] ?? null) === 'gpt-4o-mini'
            && ($body['messages'][0]['role'] ?? null) === 'system'
            && str_contains((string) ($body['messages'][0]['content'] ?? ''), 'Workspace context:')
            && ($body['messages'][1]['content'] ?? null) === 'Summarise controls';
    });
});

test('OpenAiProvider rejects missing api key', function () {
    config(['ai.providers.openai.api_key' => '']);

    expect(fn() => (new OpenAiProvider)->complete([
        ['role' => 'user', 'content' => 'Hello'],
    ]))->toThrow(ValidationException::class);
});

test('AnthropicAiProvider posts messages and returns text blocks', function () {
    config([
        'ai.providers.anthropic.api_key' => 'test-anthropic-key',
        'ai.providers.anthropic.model' => 'claude-sonnet-4-20250514',
        'ai.providers.anthropic.base_url' => 'https://api.anthropic.com/v1',
    ]);

    Http::fake([
        'api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg-test',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => 'Anthropic grounded answer.'],
            ],
        ], 200),
    ]);

    $result = (new AnthropicAiProvider)->complete([
        ['role' => 'user', 'content' => 'Any policy gaps?'],
    ], [
        'context' => '## Approved policies\nTotal: 0',
    ]);

    expect($result['provider'])->toBe(AiProviderDriver::Anthropic->value)
        ->and($result['content'])->toContain('Anthropic grounded answer');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-anthropic-key')
            && ($body['model'] ?? null) === 'claude-sonnet-4-20250514'
            && str_contains((string) ($body['system'] ?? ''), 'Workspace context:')
            && ($body['messages'][0]['content'] ?? null) === 'Any policy gaps?';
    });
});

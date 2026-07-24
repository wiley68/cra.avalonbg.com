<?php

use App\Enums\AiProviderDriver;
use App\Services\Ai\AnthropicAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\StubAiProvider;
use App\Services\AiAssistantService;
use Illuminate\Support\Facades\Artisan;

test('phpunit and default config keep stub AI provider for CI', function () {
    expect(config('ai.provider'))->toBe(AiProviderDriver::Stub->value)
        ->and(config('ai.embeddings.provider'))->toBe('stub')
        ->and(AiAssistantService::makeProvider())->toBeInstanceOf(StubAiProvider::class);
});

test('makeProvider resolves openai and anthropic drivers', function () {
    expect(AiAssistantService::makeProvider(AiProviderDriver::OpenAi->value))
        ->toBeInstanceOf(OpenAiProvider::class)
        ->and(AiAssistantService::makeProvider(AiProviderDriver::Anthropic->value))
        ->toBeInstanceOf(AnthropicAiProvider::class);
});

test('ops ai-check passes for stub without external calls', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => AiProviderDriver::Stub->value,
    ]);

    $exit = Artisan::call('ops:ai-check');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('AI config check passed')
        ->and($output)->toContain('Imported finding')
        ->and($output)->toContain('Vulnerability register')
        ->and($output)->toContain('stub');
});

test('ops ai-check fails when openai is selected without api key', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => AiProviderDriver::OpenAi->value,
        'ai.providers.openai.api_key' => '',
    ]);

    $exit = Artisan::call('ops:ai-check');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('API_KEY is empty')
        ->and($output)->toContain('AI config check failed');
});

test('ops ai-check passes when openai has api key configured', function () {
    config([
        'ai.enabled' => true,
        'ai.provider' => AiProviderDriver::OpenAi->value,
        'ai.providers.openai.api_key' => 'sk-test-not-real',
        'ai.providers.openai.model' => 'gpt-4o-mini',
    ]);

    $exit = Artisan::call('ops:ai-check');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('API key: set')
        ->and($output)->not->toContain('sk-test-not-real')
        ->and($output)->toContain('AI config check passed');
});

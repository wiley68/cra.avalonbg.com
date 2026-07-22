<?php

use App\Contracts\AiProvider;
use App\Enums\AiProviderDriver;
use App\Services\Ai\StubAiProvider;
use App\Services\AiAssistantService;
use Illuminate\Validation\ValidationException;

test('config exposes CRA_AI_ENABLED and stub provider defaults', function () {
    expect(config('ai.enabled'))->toBeBool()
        ->and(config('ai.provider'))->toBe(AiProviderDriver::Stub->value)
        ->and(config('ai.providers.openai'))->toHaveKeys(['api_key', 'model'])
        ->and(config('ai.providers.anthropic'))->toHaveKeys(['api_key', 'model']);
});

test('container resolves AiProvider to stub by default', function () {
    config(['ai.provider' => AiProviderDriver::Stub->value]);

    $provider = app(AiProvider::class);

    expect($provider)->toBeInstanceOf(StubAiProvider::class);
});

test('stub provider returns canned echo template for the last user message', function () {
    $provider = new StubAiProvider;

    $result = $provider->complete([
        ['role' => 'system', 'content' => 'You are a CRA helper.'],
        ['role' => 'user', 'content' => 'What evidence is missing?'],
    ], [
        'context' => 'Product: Demo; Requirements: 3',
    ]);

    expect($result['provider'])->toBe(AiProviderDriver::Stub->value)
        ->and($result['model'])->toBe('stub-local-template')
        ->and($result['content'])->toContain('CRA AI stub')
        ->and($result['content'])->toContain('What evidence is missing?')
        ->and($result['content'])->toContain('Workspace context was supplied')
        ->and($result['content'])->toContain('Grounded workspace context')
        ->and($result['content'])->toContain('Product: Demo; Requirements: 3')
        ->and($result['content'])->toContain('Human review is required');
});

test('AiAssistantService complete uses provider when enabled', function () {
    config(['ai.enabled' => true]);

    $service = app(AiAssistantService::class);

    $result = $service->complete([
        ['role' => 'user', 'content' => 'Summarise controls'],
    ]);

    expect($service->isEnabled())->toBeTrue()
        ->and($service->driver())->toBe(AiProviderDriver::Stub)
        ->and($result['content'])->toContain('Summarise controls')
        ->and($result['provider'])->toBe('stub');
});

test('AiAssistantService rejects complete when AI is disabled', function () {
    config(['ai.enabled' => false]);

    $service = app(AiAssistantService::class);

    expect($service->isEnabled())->toBeFalse();

    expect(fn() => $service->complete([
        ['role' => 'user', 'content' => 'Hello'],
    ]))->toThrow(ValidationException::class);
});

test('makeProvider rejects unimplemented openai and anthropic drivers', function () {
    expect(fn() => AiAssistantService::makeProvider(AiProviderDriver::OpenAi->value))
        ->toThrow(InvalidArgumentException::class);

    expect(fn() => AiAssistantService::makeProvider(AiProviderDriver::Anthropic->value))
        ->toThrow(InvalidArgumentException::class);
});

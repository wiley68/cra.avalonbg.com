<?php

use App\Enums\AiProviderDriver;
use App\Services\Ai\OpenAiProvider;
use App\Services\AiAssistantService;

/**
 * Opt-in live OpenAI call. Requires CRA_AI_OPENAI_API_KEY and CRA_AI_LIVE_TEST=true.
 *
 * php artisan test --group=live-ai
 */
test('live OpenAI provider returns a real completion', function () {
    $live = filter_var(env('CRA_AI_LIVE_TEST', false), FILTER_VALIDATE_BOOL);
    $apiKey = trim((string) config('ai.providers.openai.api_key'));

    if (!$live || $apiKey === '') {
        $this->markTestSkipped('Set CRA_AI_LIVE_TEST=true and CRA_AI_OPENAI_API_KEY to run live OpenAI tests.');
    }

    config([
        'ai.enabled' => true,
        'ai.provider' => AiProviderDriver::OpenAi->value,
    ]);

    $provider = AiAssistantService::makeProvider(AiProviderDriver::OpenAi->value);
    expect($provider)->toBeInstanceOf(OpenAiProvider::class);

    $result = $provider->complete([
        ['role' => 'user', 'content' => 'Reply with exactly the word PONG and nothing else.'],
    ], [
        'context' => '## Product\nName: Live OpenAI smoke test',
    ]);

    expect($result['provider'])->toBe(AiProviderDriver::OpenAi->value)
        ->and($result['content'])->not->toBeEmpty()
        ->and(mb_strtoupper($result['content']))->toContain('PONG');
})->group('live-ai');

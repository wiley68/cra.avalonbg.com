<?php

namespace App\Console\Commands;

use App\Enums\AiProviderDriver;
use Illuminate\Console\Command;

class OpsAiCheck extends Command
{
    protected $signature = 'ops:ai-check';

    protected $description = 'Verify AI / live LLM configuration for Phase 2_E Must 3 (no external API calls)';

    public function handle(): int
    {
        $ok = true;

        $enabled = (bool) config('ai.enabled');
        $provider = (string) config('ai.provider', AiProviderDriver::Stub->value);
        $driver = AiProviderDriver::tryFrom($provider) ?? AiProviderDriver::Stub;

        $this->info('CRA_AI_ENABLED: ' . ($enabled ? 'true' : 'false'));
        $this->info("CRA_AI_PROVIDER: {$driver->value}");

        if (!$enabled) {
            $this->warn('AI is disabled — triage buttons will refuse requests until CRA_AI_ENABLED=true.');
        }

        $queueEnabled = (bool) config('ai.queue.enabled');
        $this->line('CRA_AI_QUEUE_ENABLED: ' . ($queueEnabled ? 'true' : 'false'));
        if ($queueEnabled) {
            $this->line('Note: queued analyse/draft/triage/RAG index needs `php artisan queue:work` (chat stays sync).');
        }

        $ragEnabled = (bool) config('ai.rag.enabled', true);
        $ragSchedule = strtolower((string) config('ai.rag.reindex_schedule', 'daily'));
        $ragAt = (string) config('ai.rag.reindex_at', '02:30');
        $this->line('CRA_AI_RAG_ENABLED: ' . ($ragEnabled ? 'true' : 'false'));
        $this->line("CRA_AI_RAG_REINDEX_SCHEDULE: {$ragSchedule}" . ($ragSchedule === 'daily' ? " @ {$ragAt}" : ''));
        if ($ragEnabled && $ragSchedule !== 'off') {
            $this->line('Note: scheduler runs `ai:index-embeddings` (queues jobs unless --sync). See Phase2_E_Live_LLM_Enablement.md § RAG.');
        } elseif (!$ragEnabled) {
            $this->line('Note: RAG disabled — scheduled reindex is skipped by when() / command no-op.');
        } else {
            $this->line('Note: RAG reindex schedule is off — run `php artisan ai:index-embeddings` manually when needed.');
        }

        match ($driver) {
            AiProviderDriver::Stub => $this->line('Stub provider: OK for CI/tests; triage returns canned drafts (no external calls).'),
            AiProviderDriver::OpenAi => $ok = $this->assertLiveKey(
                'openai',
                (string) config('ai.providers.openai.api_key'),
                (string) config('ai.providers.openai.model', 'gpt-4o-mini'),
            ) && $ok,
            AiProviderDriver::Anthropic => $ok = $this->assertLiveKey(
                'anthropic',
                (string) config('ai.providers.anthropic.api_key'),
                (string) config('ai.providers.anthropic.model', 'claude-sonnet-4-20250514'),
            ) && $ok,
        };

        $this->newLine();
        $this->line('Must 3 triage surfaces (human review; no auto-accept):');
        $this->line('  1) Imported finding: Product Edit → pending Snyk/SARIF/VCS vuln suggestion → AI triage summary');
        $this->line('  2) Vulnerability register: Product → Vulnerabilities → Edit → AI triage suggestions');
        $this->line('CI default: phpunit.xml sets CRA_AI_PROVIDER=stub (never live keys in CI).');
        $this->line('Opt-in live smoke: CRA_AI_LIVE_TEST=true + key → php artisan test --group=live-ai');
        $this->line('Guide: documents/Phase2_E_Live_LLM_Enablement.md');

        if ($ok) {
            $this->newLine();
            $this->info('AI config check passed.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error('AI config check failed.');

        return self::FAILURE;
    }

    private function assertLiveKey(string $name, string $apiKey, string $model): bool
    {
        $this->line("{$name} model: {$model}");

        if (trim($apiKey) === '') {
            $this->error("CRA_AI_" . strtoupper($name) . "_API_KEY is empty — live {$name} calls will fail with provider_misconfigured.");

            return false;
        }

        $this->info("{$name} API key: set (" . strlen(trim($apiKey)) . ' chars; value not printed)');

        return true;
    }
}

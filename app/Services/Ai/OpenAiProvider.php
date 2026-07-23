<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Enums\AiProviderDriver;
use App\Support\Translations;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class OpenAiProvider implements AiProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{context?: string|null}  $options
     * @return array{content: string, provider: string, model: string|null}
     */
    public function complete(array $messages, array $options = []): array
    {
        $apiKey = trim((string) config('ai.providers.openai.api_key'));
        $model = (string) config('ai.providers.openai.model', 'gpt-4o-mini');
        $baseUrl = rtrim((string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = max(5, (int) config('ai.providers.openai.timeout', 60));

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_misconfigured'),
            ]);
        }

        $system = AiSystemPrompt::build($options['context'] ?? null);
        $mode = $options['mode'] ?? null;
        if ($mode === 'document_analyse') {
            $system .= "\n\n" . AiDocumentAnalysePrompt::systemAddon();
        } elseif ($mode === 'draft_generate') {
            $system .= "\n\n" . AiDraftPrompt::systemAddon();
        } elseif ($mode === 'usi_section_draft') {
            $system .= "\n\n" . AiUsiSectionDraftPrompt::systemAddon();
        } elseif ($mode === 'incident_summary') {
            $system .= "\n\n" . AiIncidentSummaryDraftPrompt::systemAddon();
        } elseif ($mode === 'sdl_stage_notes') {
            $system .= "\n\n" . AiSdlStageNotesDraftPrompt::systemAddon();
        } elseif ($mode === 'vulnerability_triage') {
            $system .= "\n\n" . AiVulnerabilityTriagePrompt::systemAddon();
        }

        $payloadMessages = [
            [
                'role' => 'system',
                'content' => $system,
            ],
        ];

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

        $payload = [
            'model' => $model,
            'messages' => $payloadMessages,
            'temperature' => 0.2,
        ];

        if (in_array($mode, ['document_analyse', 'draft_generate', 'usi_section_draft', 'incident_summary', 'sdl_stage_notes', 'vulnerability_triage'], true)) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout($timeout)
                ->post('/chat/completions', $payload)
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

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_failed'),
            ]);
        }

        $resolvedModel = data_get($response->json(), 'model');

        return [
            'content' => $content,
            'provider' => AiProviderDriver::OpenAi->value,
            'model' => is_string($resolvedModel) && $resolvedModel !== '' ? $resolvedModel : $model,
        ];
    }
}

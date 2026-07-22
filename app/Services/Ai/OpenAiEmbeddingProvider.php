<?php

namespace App\Services\Ai;

use App\Contracts\EmbeddingProvider;
use App\Enums\EmbeddingProviderDriver;
use App\Support\Translations;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class OpenAiEmbeddingProvider implements EmbeddingProvider
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $apiKey = trim((string) config('ai.providers.openai.api_key'));
        $baseUrl = rtrim((string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'), '/');
        $timeout = max(5, (int) config('ai.providers.openai.timeout', 60));
        $model = $this->model();

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_misconfigured'),
            ]);
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            $trimmed = '(empty)';
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($apiKey)
                ->acceptJson()
                ->timeout($timeout)
                ->post('/embeddings', [
                    'model' => $model,
                    'input' => $trimmed,
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

        /** @var mixed $raw */
        $raw = data_get($response->json(), 'data.0.embedding');
        if (!is_array($raw) || $raw === []) {
            throw ValidationException::withMessages([
                'assistant' => Translations::get('assistant.provider_failed'),
            ]);
        }

        return array_values(array_map(static fn($v): float => (float) $v, $raw));
    }

    public function model(): string
    {
        return (string) config('ai.embeddings.openai_model', 'text-embedding-3-small');
    }

    public function dimensions(): int
    {
        // OpenAI may return model-native dimensions; indexer stores actual length.
        return max(8, (int) config('ai.embeddings.dimensions', 1536));
    }

    public function driver(): string
    {
        return EmbeddingProviderDriver::OpenAi->value;
    }
}

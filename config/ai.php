<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRA AI assistant
    |--------------------------------------------------------------------------
    |
    | Feature flag + provider selection. Default driver is the local stub.
    | OpenAI / Anthropic adapters share the AiProvider contract.
    |
    */

    'enabled' => (bool) env('CRA_AI_ENABLED', true),

    /*
    | Supported drivers: stub | openai | anthropic
    */
    'provider' => env('CRA_AI_PROVIDER', 'stub'),

    /*
    | Plain-text context builder budgets (local summaries, no RAG).
    */
    'context_max_chars' => max(500, (int) env('CRA_AI_CONTEXT_MAX_CHARS', 8000)),
    'context_requirements_limit' => max(1, (int) env('CRA_AI_CONTEXT_REQUIREMENTS_LIMIT', 40)),
    'context_controls_limit' => max(1, (int) env('CRA_AI_CONTEXT_CONTROLS_LIMIT', 40)),
    'context_excerpt_chars' => max(80, (int) env('CRA_AI_CONTEXT_EXCERPT_CHARS', 400)),
    'analyse_max_chars' => max(1000, (int) env('CRA_AI_ANALYSE_MAX_CHARS', 20000)),
    'analyse_max_upload_kb' => max(64, (int) env('CRA_AI_ANALYSE_MAX_UPLOAD_KB', 2048)),

    'providers' => [
        'stub' => [
            // No credentials — returns a local canned/echo template.
        ],
        'openai' => [
            'api_key' => env('CRA_AI_OPENAI_API_KEY'),
            'model' => env('CRA_AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('CRA_AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => max(5, (int) env('CRA_AI_OPENAI_TIMEOUT', 60)),
        ],
        'anthropic' => [
            'api_key' => env('CRA_AI_ANTHROPIC_API_KEY'),
            'model' => env('CRA_AI_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'base_url' => env('CRA_AI_ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'timeout' => max(5, (int) env('CRA_AI_ANTHROPIC_TIMEOUT', 60)),
            'version' => env('CRA_AI_ANTHROPIC_VERSION', '2023-06-01'),
            'max_tokens' => max(256, (int) env('CRA_AI_ANTHROPIC_MAX_TOKENS', 2048)),
        ],
    ],

];

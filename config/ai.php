<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRA AI assistant
    |--------------------------------------------------------------------------
    |
    | Feature flag + provider selection. Must uses the local stub (canned/echo
    | template). Real OpenAI/Anthropic adapters land behind the same
    | AiProvider contract later — credentials are placeholders only for now.
    |
    */

    'enabled' => (bool) env('CRA_AI_ENABLED', true),

    /*
    | Supported drivers: stub (default). openai / anthropic reserved for Should.
    */
    'provider' => env('CRA_AI_PROVIDER', 'stub'),

    /*
    | Plain-text context builder budgets (Must — local summaries, no RAG).
    */
    'context_max_chars' => max(500, (int) env('CRA_AI_CONTEXT_MAX_CHARS', 8000)),
    'context_requirements_limit' => max(1, (int) env('CRA_AI_CONTEXT_REQUIREMENTS_LIMIT', 40)),
    'context_controls_limit' => max(1, (int) env('CRA_AI_CONTEXT_CONTROLS_LIMIT', 40)),
    'context_excerpt_chars' => max(80, (int) env('CRA_AI_CONTEXT_EXCERPT_CHARS', 400)),

    'providers' => [
        'stub' => [
            // No credentials — returns a local canned/echo template.
        ],
        'openai' => [
            'api_key' => env('CRA_AI_OPENAI_API_KEY'),
            'model' => env('CRA_AI_OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'anthropic' => [
            'api_key' => env('CRA_AI_ANTHROPIC_API_KEY'),
            'model' => env('CRA_AI_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        ],
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default panel variant
    |--------------------------------------------------------------------------
    |
    | Used by the frontend Panel component when no variant is provided.
    | Keep this in sync with resources/js/config/panels.ts.
    |
    */

    'default' => 'standard',

    /*
    |--------------------------------------------------------------------------
    | Panel variants
    |--------------------------------------------------------------------------
    |
    | Semantic panel styles for consistent UI surfaces across the app
    | (similar to Bootstrap alert / Tailwind tone utilities).
    |
    */

    'variants' => [
        'standard' => [
            'label' => 'Standard',
            'description' => 'Neutral content panels and cards.',
        ],
        'info' => [
            'label' => 'Info',
            'description' => 'Informational notices and helpful context.',
        ],
        'important' => [
            'label' => 'Important',
            'description' => 'Warnings and content that needs attention.',
        ],
        'success' => [
            'label' => 'Success',
            'description' => 'Positive confirmations and completed states.',
        ],
        'error' => [
            'label' => 'Error',
            'description' => 'Errors, failures, and blocking issues.',
        ],
    ],

];

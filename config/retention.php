<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention periods (years)
    |--------------------------------------------------------------------------
    |
    | Retention windows for scheduled prune commands. Records older than the
    | threshold are permanently deleted when `schedule:run` executes.
    |
    */

    'audit_logs_years' => (int) env('AUDIT_LOG_RETENTION_YEARS', 1),

];

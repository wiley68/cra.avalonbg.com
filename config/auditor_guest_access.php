<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auditor guest magic-link access
    |--------------------------------------------------------------------------
    |
    | Time-limited opaque tokens let an external auditor open a shared review
    | package without an org user account (view-only). Default lifetime is in
    | days; owners can revoke or rotate the link at any time.
    |
    */

    'guest_link_ttl_days' => max(1, (int) env('CRA_AUDITOR_GUEST_LINK_TTL_DAYS', 7)),

];

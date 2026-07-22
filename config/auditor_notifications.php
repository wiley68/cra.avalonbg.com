<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Auditor review-package share email notifications (stub)
    |--------------------------------------------------------------------------
    |
    | When an owner shares a review package, queue Laravel Mailables to org
    | members with the auditor role. Delivery uses the default mailer
    | (log/array/smtp). Real provider credentials are configured later via
    | MAIL_* — this flag only gates whether the app queues sends at all.
    |
    */

    'enabled' => (bool) env('CRA_AUDITOR_NOTIFICATIONS_ENABLED', true),

];

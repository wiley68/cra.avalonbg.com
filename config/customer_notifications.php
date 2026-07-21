<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer patch-campaign email notifications (stub)
    |--------------------------------------------------------------------------
    |
    | Queues Laravel Mailables for campaign targets. Delivery uses the default
    | mailer (log/array/smtp). Real provider credentials are configured later
    | via MAIL_* — this flag only gates whether the app queues sends at all.
    |
    */

    'enabled' => (bool) env('CRA_CUSTOMER_NOTIFICATIONS_ENABLED', true),

];

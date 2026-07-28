<?php

/**
 * SSO / IdP integration settings.
 */
return [
    'saml' => [
        /*
        |--------------------------------------------------------------------------
        | Require XML signature on SAML Responses
        |--------------------------------------------------------------------------
        |
        | Production should keep this true. Feature tests may disable it when
        | posting unsigned fixture Responses.
        |
        */
        'require_signature' => (bool) env('SSO_SAML_REQUIRE_SIGNATURE', true),
    ],
];

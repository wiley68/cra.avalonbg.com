<?php

/**
 * Phase 2_F plan catalog — product limits and provisional EUR pricing.
 *
 * @see documents/Phase2_F_Platform_Billing_SSO.md
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Canonical subscription plans
    |--------------------------------------------------------------------------
    |
    | max_products: null = unlimited (Enterprise).
    | yearly_price_eur: ~20% off 12× monthly (null when free).
    |
    */
    'plans' => [
        'free' => [
            'max_products' => 1,
            'monthly_price_eur' => 0,
            'yearly_price_eur' => null,
        ],
        'small' => [
            'max_products' => 3,
            'monthly_price_eur' => 29,
            'yearly_price_eur' => 278.40,
        ],
        'standard' => [
            'max_products' => 10,
            'monthly_price_eur' => 39,
            'yearly_price_eur' => 374.40,
        ],
        'enterprise' => [
            'max_products' => null,
            'monthly_price_eur' => 59,
            'yearly_price_eur' => 566.40,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default when subscription_plan is missing / invalid
    |--------------------------------------------------------------------------
    */
    'default_plan' => 'free',
];

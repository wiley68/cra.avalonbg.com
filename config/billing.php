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

    /*
    |--------------------------------------------------------------------------
    | Bank transfer instructions (invoicing remains outside the app)
    |--------------------------------------------------------------------------
    */
    'bank' => [
        'beneficiary' => env('BILLING_BANK_BENEFICIARY', 'Avalon BG EOOD'),
        'iban' => env('BILLING_BANK_IBAN', ''),
        'bic' => env('BILLING_BANK_BIC', ''),
        'bank_name' => env('BILLING_BANK_NAME', ''),
        'reference_prefix' => env('BILLING_BANK_REFERENCE_PREFIX', 'CRA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Checkout / Subscriptions
    |--------------------------------------------------------------------------
    |
    | Optional price IDs override dynamic price_data. When empty, Checkout uses
    | recurring price_data from the plan catalog (EUR).
    |
    */
    'stripe' => [
        'secret' => env('STRIPE_SECRET', ''),
        'publishable' => env('STRIPE_KEY', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
        'prices' => [
            'small' => [
                'month' => env('STRIPE_PRICE_SMALL_MONTH', ''),
                'year' => env('STRIPE_PRICE_SMALL_YEAR', ''),
            ],
            'standard' => [
                'month' => env('STRIPE_PRICE_STANDARD_MONTH', ''),
                'year' => env('STRIPE_PRICE_STANDARD_YEAR', ''),
            ],
            'enterprise' => [
                'month' => env('STRIPE_PRICE_ENTERPRISE_MONTH', ''),
                'year' => env('STRIPE_PRICE_ENTERPRISE_YEAR', ''),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dunning / past_due
    |--------------------------------------------------------------------------
    |
    | Soft grace after Stripe marks the subscription past_due. Existing data
    | is never auto-deleted; new product creation stays locked until active.
    |
    */
    'dunning' => [
        'grace_days' => (int) env('BILLING_DUNNING_GRACE_DAYS', 14),
    ],
];

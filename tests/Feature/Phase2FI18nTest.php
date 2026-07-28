<?php

use App\Enums\AuditEventType;
use App\Support\Translations;

/**
 * Must 8 — Phase 2_F i18n guardrails (EN + BG parity for billing / SSO / signup).
 */
test('phase 2_F billing and sso translation trees have matching keys in en and bg', function () {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);
    $bg = json_decode((string) file_get_contents(lang_path('bg.json')), true);

    expect($en)->toBeArray()->and($bg)->toBeArray();

    $flatten = function (array $node, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $keys = [...$keys, ...$flatten($value, $path)];
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    };

    foreach (['billing', 'sso', 'auth'] as $section) {
        expect($en[$section] ?? null)->toBeArray("en.{$section} missing")
            ->and($bg[$section] ?? null)->toBeArray("bg.{$section} missing");
    }

    $enBilling = $flatten($en['billing'], 'billing');
    $bgBilling = $flatten($bg['billing'], 'billing');
    $enSso = $flatten($en['sso'], 'sso');
    $bgSso = $flatten($bg['sso'], 'sso');
    $enRegister = $flatten($en['auth']['register'] ?? [], 'auth.register');
    $bgRegister = $flatten($bg['auth']['register'] ?? [], 'auth.register');

    expect(array_diff($enBilling, $bgBilling))->toBe([])
        ->and(array_diff($bgBilling, $enBilling))->toBe([])
        ->and(array_diff($enSso, $bgSso))->toBe([])
        ->and(array_diff($bgSso, $enSso))->toBe([])
        ->and(array_diff($enRegister, $bgRegister))->toBe([])
        ->and(array_diff($bgRegister, $enRegister))->toBe([]);
});

test('phase 2_F critical ui keys resolve in english and bulgarian', function () {
    $keys = [
        'billing.title',
        'billing.request_bank_payment',
        'billing.stripe.checkout',
        'billing.stripe.manage',
        'billing.change_plan.title',
        'billing.current_interval',
        'billing.documents.title',
        'sso.title',
        'sso.errors.domain_rejected',
        'sso.errors.unknown_user',
        'sso.errors.plan_not_allowed',
        'auth.register.title',
        'auth.register.paid_pending_note',
        'auth.login.sso_submit',
        'settings.nav.billing',
        'settings.nav.sso',
        'products.plan_product_limit',
        'products.plan_pending_payment',
        'products.create_disabled_limit',
        'products.create_disabled_pending',
        'admin.organizations.sso_enabled',
        'admin.organizations.billing_activated',
        'billing.plans.free',
        'billing.plans.small',
        'billing.plans.standard',
        'billing.plans.enterprise',
        'billing.status.pending_payment',
        'billing.status.active',
    ];

    foreach ($keys as $key) {
        $en = Translations::get($key, locale: 'en');
        $bg = Translations::get($key, locale: 'bg');

        expect($en)->not->toBe($key, "en missing: {$key}")
            ->and($bg)->not->toBe($key, "bg missing: {$key}")
            ->and($en)->not->toBe('')
            ->and($bg)->not->toBe('');
    }
});

test('phase 2_F billing and sso audit event labels resolve in both locales', function () {
    $types = [
        AuditEventType::BankPaymentRequested,
        AuditEventType::BillingActivated,
        AuditEventType::BillingDocumentUploaded,
        AuditEventType::BillingDocumentSent,
        AuditEventType::BillingDocumentDeleted,
        AuditEventType::StripeCheckoutStarted,
        AuditEventType::StripeSubscriptionUpdated,
        AuditEventType::StripeSubscriptionRenewed,
        AuditEventType::SubscriptionPlanChanged,
        AuditEventType::SsoConnectionCreated,
        AuditEventType::SsoConnectionUpdated,
        AuditEventType::SsoConnectionDeleted,
        AuditEventType::SsoLoginSuccess,
    ];

    foreach ($types as $type) {
        $key = 'audit_logs.event_types.' . $type->value;

        expect(Translations::get($key, locale: 'en'))->not->toBe($key)
            ->and(Translations::get($key, locale: 'bg'))->not->toBe($key);
    }
});

test('phase 2_F translations endpoint serves billing and sso for both locales', function () {
    $this->get(route('translations.show', ['locale' => 'en']))
        ->assertOk()
        ->assertJsonPath('billing.title', Translations::get('billing.title', locale: 'en'))
        ->assertJsonPath('sso.title', Translations::get('sso.title', locale: 'en'))
        ->assertJsonPath('auth.register.submit', Translations::get('auth.register.submit', locale: 'en'));

    $this->get(route('translations.show', ['locale' => 'bg']))
        ->assertOk()
        ->assertJsonPath('billing.title', Translations::get('billing.title', locale: 'bg'))
        ->assertJsonPath('sso.title', Translations::get('sso.title', locale: 'bg'))
        ->assertJsonPath('auth.register.submit', Translations::get('auth.register.submit', locale: 'bg'));
});

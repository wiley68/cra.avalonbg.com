<?php

use App\Enums\SubscriptionPlan;
use App\Support\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('paid plan yearly prices are about 20 percent off twelve monthly payments', function () {
    foreach ([SubscriptionPlan::Small, SubscriptionPlan::Standard, SubscriptionPlan::Enterprise] as $plan) {
        $monthlyYear = round($plan->monthlyPriceEur() * 12, 2);
        $yearly = $plan->yearlyPriceEur();

        expect($yearly)->not->toBeNull();

        $discountRatio = 1 - ($yearly / $monthlyYear);

        expect($discountRatio)->toBeGreaterThan(0.19)
            ->and($discountRatio)->toBeLessThan(0.21)
            ->and($yearly)->toBeLessThan($monthlyYear);
    }
});

test('annual pricing messaging keys resolve in english and bulgarian', function () {
    $keysWithTwenty = [
        'billing.annual.badge',
        'billing.annual.callout',
        'billing.annual.save_amount',
        'billing.annual.or_yearly',
        'auth.register.annual_callout',
        'auth.register.interval_year',
        'billing.interval.year',
        'billing.change_plan.annual_hint',
    ];

    foreach ($keysWithTwenty as $key) {
        $en = Translations::get($key, locale: 'en');
        $bg = Translations::get($key, locale: 'bg');

        expect($en)->not->toBe($key)
            ->and($bg)->not->toBe($key)
            ->and($en)->toContain('20')
            ->and($bg)->toContain('20');
    }

    $vsMonthlyEn = Translations::get('billing.annual.vs_monthly_year', locale: 'en');
    $vsMonthlyBg = Translations::get('billing.annual.vs_monthly_year', locale: 'bg');

    expect($vsMonthlyEn)->not->toBe('billing.annual.vs_monthly_year')
        ->and($vsMonthlyBg)->not->toBe('billing.annual.vs_monthly_year')
        ->and($vsMonthlyEn)->toContain(':price')
        ->and($vsMonthlyBg)->toContain(':price');
});

test('registration screen exposes yearly plan prices for annual messaging', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auth/Register')
            ->has('subscriptionPlans', 4)
            ->where('subscriptionPlans.1.value', SubscriptionPlan::Small->value)
            ->where('subscriptionPlans.1.monthly_price_eur', 29)
            ->where('subscriptionPlans.1.yearly_price_eur', 278.4));
});

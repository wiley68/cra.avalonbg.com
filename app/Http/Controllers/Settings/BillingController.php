<?php

namespace App\Http\Controllers\Settings;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Services\BankPaymentService;
use App\Services\BillingDocumentService;
use App\Services\StripeBillingService;
use App\Services\TenantBillingService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly BankPaymentService $bankPayments,
        private readonly BillingDocumentService $documents,
        private readonly StripeBillingService $stripeBilling,
        private readonly TenantBillingService $tenantBilling,
    ) {
    }

    public function edit(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $organization->syncExpiredTrial();

        $pending = $this->bankPayments->pendingRequest($organization);
        $billingActive = $organization->isBillingActive();
        $onTrial = $organization->isOnTrial();
        $paidPlan = $organization->resolvedSubscriptionPlan()->value !== 'free';
        $canManageStripe = $this->tenantBilling->canManageStripe($organization);
        $needsPayment = $paidPlan && !$organization->hasConfirmedPaidSubscription();

        return Inertia::render('settings/Billing', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'subscription_plan' => $organization->resolvedSubscriptionPlan()->value,
                'billing_status' => $organization->resolvedBillingStatus()->value,
                'billing_interval' => $organization->billing_interval?->value,
                'billing_email' => $organization->billing_email,
                'payment_method' => $organization->payment_method?->value,
                'billing_activated_at' => $organization->billing_activated_at?->toIso8601String(),
                'trial_ends_at' => $organization->trial_ends_at?->toIso8601String(),
                'promo_code' => $organization->promo_code,
                'on_trial' => $onTrial,
            ],
            'subscriptionPlans' => SubscriptionPlan::catalogPayload(),
            'pendingRequest' => $this->bankPayments->requestPayload($pending),
            'bankInstructions' => $this->bankPayments->bankInstructions(),
            'canRequestBankPayment' => $needsPayment
                && $pending === null
                && !$organization->isBillingCancelled(),
            'canCheckoutStripe' => $this->stripeBilling->isConfigured()
                && $needsPayment
                && !$organization->isBillingCancelled(),
            'canManageStripe' => $canManageStripe,
            'canChangePlan' => $this->tenantBilling->canChangePlanInApp($organization),
            'canApplyPromo' => $this->tenantBilling->canApplyPromo($organization),
            'stripeConfigured' => $this->stripeBilling->isConfigured(),
            'documents' => $billingActive
                ? $this->documents->listPayload($organization)
                : [],
            'canManageDocuments' => false,
        ]);
    }

    public function changePlan(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'subscription_plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'billing_interval' => [
                'nullable',
                Rule::enum(BillingInterval::class),
                Rule::requiredIf(fn() => $request->input('subscription_plan') !== SubscriptionPlan::Free->value),
            ],
        ]);

        $plan = SubscriptionPlan::from($validated['subscription_plan']);
        $interval = isset($validated['billing_interval'])
            ? BillingInterval::from($validated['billing_interval'])
            : null;

        $this->tenantBilling->changePlan(
            $organization,
            $request->user(),
            $plan,
            $interval,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.change_plan.updated'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function applyPromo(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'promo_code' => ['required', 'string', 'max:64'],
        ]);

        $this->tenantBilling->applyPromoCode(
            $organization,
            $validated['promo_code'],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.promo.applied'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function requestBankPayment(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $this->bankPayments->createRequest(
            $organization,
            $request->user(),
            $request->input('notes'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.request_created'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function checkoutStripe(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $checkout = $this->stripeBilling->createCheckoutSession(
            $organization,
            $request->user(),
        );

        return redirect()->away($checkout['url']);
    }

    public function manageStripe(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $portal = $this->stripeBilling->createCustomerPortalSession($organization);

        return redirect()->away($portal['url']);
    }

    public function stripeSuccess(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.stripe.success'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function stripeCancel(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => Translations::get('billing.stripe.cancelled'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function downloadDocument(OrganizationBillingDocument $document): StreamedResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        if (!$organization->isBillingActive()) {
            abort(403);
        }

        return $this->documents->download($document);
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertDocumentBelongs(
        Organization $organization,
        OrganizationBillingDocument $document,
    ): void {
        if ($document->organization_id !== $organization->id) {
            abort(404);
        }
    }
}

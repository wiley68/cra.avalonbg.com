<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Services\BankPaymentService;
use App\Services\BillingDocumentService;
use App\Services\StripeBillingService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly BankPaymentService $bankPayments,
        private readonly BillingDocumentService $documents,
        private readonly StripeBillingService $stripeBilling,
    ) {
    }

    public function edit(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $pending = $this->bankPayments->pendingRequest($organization);
        $billingActive = $organization->isBillingActive();
        $paidPlan = $organization->resolvedSubscriptionPlan()->value !== 'free';

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
            ],
            'pendingRequest' => $this->bankPayments->requestPayload($pending),
            'bankInstructions' => $this->bankPayments->bankInstructions(),
            'canRequestBankPayment' => !$billingActive
                && $paidPlan
                && $pending === null,
            'canCheckoutStripe' => $this->stripeBilling->isConfigured()
                && $paidPlan
                && !$billingActive,
            'stripeConfigured' => $this->stripeBilling->isConfigured(),
            'documents' => $billingActive
                ? $this->documents->listPayload($organization)
                : [],
            'canManageDocuments' => false,
        ]);
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

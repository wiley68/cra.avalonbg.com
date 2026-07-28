<?php

namespace App\Http\Controllers\Settings;

use App\Enums\BillingDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillingDocumentRequest;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Services\BankPaymentService;
use App\Services\BillingDocumentService;
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
    ) {
    }

    public function edit(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $pending = $this->bankPayments->pendingRequest($organization);

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
            'canRequestBankPayment' => !$organization->isBillingActive()
                && $organization->resolvedSubscriptionPlan()->value !== 'free'
                && $pending === null,
            'documents' => $this->documents->listPayload($organization),
            'documentRecipientEmail' => $this->documents->resolveRecipientEmail($organization),
            'documentTypes' => BillingDocumentType::values(),
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

    public function storeDocument(StoreBillingDocumentRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);

        $this->documents->upload(
            $organization,
            $request->user(),
            $request->file('file'),
            BillingDocumentType::from($request->string('type')->toString()),
            $request->input('title'),
            $request->input('notes'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.documents.uploaded'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function downloadDocument(OrganizationBillingDocument $document): StreamedResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        return $this->documents->download($document);
    }

    public function sendDocument(
        Request $request,
        OrganizationBillingDocument $document,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        $this->documents->send($document, $request->user(), $organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.documents.sent'),
        ]);

        return redirect()->route('settings.billing.edit');
    }

    public function destroyDocument(
        Request $request,
        OrganizationBillingDocument $document,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        $this->documents->delete($document, $request->user(), $organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.documents.deleted'),
        ]);

        return redirect()->route('settings.billing.edit');
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

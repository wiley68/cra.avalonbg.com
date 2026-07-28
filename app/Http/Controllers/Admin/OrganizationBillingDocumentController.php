<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillingDocumentRequest;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Services\BillingDocumentService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationBillingDocumentController extends Controller
{
    public function __construct(
        private readonly BillingDocumentService $documents,
    ) {
    }

    public function store(
        StoreBillingDocumentRequest $request,
        Organization $organization,
    ): RedirectResponse {
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

        return redirect()->route('admin.organizations.billing', $organization);
    }

    public function download(
        Organization $organization,
        OrganizationBillingDocument $document,
    ): StreamedResponse {
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        return $this->documents->download($document);
    }

    public function send(
        Request $request,
        Organization $organization,
        OrganizationBillingDocument $document,
    ): RedirectResponse {
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        $this->documents->send($document, $request->user(), $organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.documents.sent'),
        ]);

        return redirect()->route('admin.organizations.billing', $organization);
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationBillingDocument $document,
    ): RedirectResponse {
        $this->authorize('update', $organization);
        $this->assertDocumentBelongs($organization, $document);

        $this->documents->delete($document, $request->user(), $organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('billing.documents.deleted'),
        ]);

        return redirect()->route('admin.organizations.billing', $organization);
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

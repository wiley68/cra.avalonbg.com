<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuditorFindingRequest;
use App\Http\Requests\UpdateAuditorFindingRequest;
use App\Http\Requests\UpdateAuditorFindingStatusRequest;
use App\Enums\AuditorFindingStatus;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Services\AuditorFindingService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AuditorFindingController extends Controller
{
    public function __construct(
        private readonly AuditorFindingService $findings,
    ) {
    }

    public function store(
        StoreAuditorFindingRequest $request,
        AuditorReviewPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);

        $this->findings->create(
            $package,
            [
                'title' => $request->string('title')->toString(),
                'body' => $request->string('body')->toString(),
                'severity' => $request->string('severity')->toString(),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.findings.created'),
        ]);

        return redirect()->route('auditor.packages.show', $package);
    }

    public function update(
        UpdateAuditorFindingRequest $request,
        AuditorReviewPackage $package,
        AuditorFinding $finding,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->assertFindingInPackage($finding, $package);

        $this->findings->updateContent(
            $finding,
            [
                'title' => $request->string('title')->toString(),
                'body' => $request->string('body')->toString(),
                'severity' => $request->string('severity')->toString(),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.findings.updated'),
        ]);

        return redirect()->route('auditor.packages.show', $package);
    }

    public function updateStatus(
        UpdateAuditorFindingStatusRequest $request,
        AuditorReviewPackage $package,
        AuditorFinding $finding,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->assertFindingInPackage($finding, $package);

        $this->findings->updateStatus(
            $finding,
            AuditorFindingStatus::from($request->string('status')->toString()),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.findings.status_updated'),
        ]);

        return redirect()->route('auditor.packages.show', $package);
    }

    public function destroy(
        AuditorReviewPackage $package,
        AuditorFinding $finding,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->assertFindingInPackage($finding, $package);
        $this->authorize('delete', [$finding, $organization]);

        $this->findings->delete($finding, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.findings.deleted'),
        ]);

        return redirect()->route('auditor.packages.show', $package);
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertPackageInOrganization(
        AuditorReviewPackage $package,
        Organization $organization,
    ): void {
        if ($package->organization_id !== $organization->id) {
            abort(404);
        }
    }

    private function assertFindingInPackage(
        AuditorFinding $finding,
        AuditorReviewPackage $package,
    ): void {
        if ($finding->package_id !== $package->id) {
            abort(404);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\TechnicalDocumentationPackage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TechnicalDocumentationOrgController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [TechnicalDocumentationPackage::class, $organization]);

        return Inertia::render('technical-documentation/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'canManage' => $request->user()->canManageTechnicalDocumentation($organization),
        ]);
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Product;
use App\Services\ComplianceWizardService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductComplianceWizardController extends Controller
{
    public function __construct(
        private readonly ComplianceWizardService $wizard,
    ) {}

    public function show(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $wizard = $this->wizard->build($product);

        return Inertia::render('products/wizard/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'product' => $wizard['product'],
            'steps' => $wizard['steps'],
            'current_step_key' => $wizard['current_step_key'],
            'required_complete' => $wizard['required_complete'],
            'success' => $wizard['success'],
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

    private function assertProductInOrganization(Product $product, Organization $organization): void
    {
        if ($product->organization_id !== $organization->id) {
            abort(404);
        }
    }
}

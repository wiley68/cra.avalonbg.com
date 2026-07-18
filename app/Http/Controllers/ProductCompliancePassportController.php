<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Product;
use App\Services\ProductReadinessService;
use App\Support\AuditLogger;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductCompliancePassportController extends Controller
{
    public function __construct(
        private readonly ProductReadinessService $readiness,
    ) {
    }

    public function show(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $product->load([
            'productOwner:id,name,email',
            'securityContact:id,name,email',
            'versions' => fn($query) => $query
                ->orderByDesc('release_date')
                ->orderByDesc('id')
                ->limit(5),
            'supportPeriods' => fn($query) => $query
                ->with(['versions:id,version_number,release_date'])
                ->orderBy('type')
                ->orderByDesc('id'),
        ]);

        $report = $this->readiness->build($product);

        AuditLogger::logCompliancePassportViewed($product, request()->user());

        return Inertia::render('products/passport/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'manufacturer' => $product->manufacturer,
                'trademark' => $product->trademark,
                'product_type' => $product->product_type?->value,
                'licensing_model' => $product->licensing_model?->value,
                'scope_status' => $product->scope_status?->value,
                'classification_status' => $product->classification_status?->value,
                'intended_purpose' => $product->intended_purpose,
                'product_owner' => $product->productOwner
                    ? [
                        'id' => $product->productOwner->id,
                        'name' => $product->productOwner->name,
                        'email' => $product->productOwner->email,
                    ]
                    : null,
                'security_contact' => $product->securityContact
                    ? [
                        'id' => $product->securityContact->id,
                        'name' => $product->securityContact->name,
                        'email' => $product->securityContact->email,
                    ]
                    : null,
                'versions' => $product->versions->map(fn($version) => [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'state' => $version->state?->value,
                    'support_status' => $version->support_status?->value,
                    'release_date' => $version->release_date?->toDateString(),
                ])->values()->all(),
                'support_periods' => $product->supportPeriods->map(fn($period) => [
                    'id' => $period->id,
                    'type' => $period->type->value,
                    'start_basis' => $period->start_basis->value,
                    'duration_months' => $period->duration_months,
                    'effective_starts_at' => $period->effectiveStartsAt()?->toDateString(),
                    'effective_ends_at' => $period->effectiveEndsAt()?->toDateString(),
                    'schedule_resolved' => $period->scheduleResolved(),
                    'basis' => $period->basis,
                    'is_extended' => $period->is_extended,
                ])->values()->all(),
            ],
            'report' => $report,
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

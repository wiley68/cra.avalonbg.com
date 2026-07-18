<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Product;
use App\Services\ProductReadinessService;
use App\Support\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductReadinessController extends Controller
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

        $report = $this->readiness->build($product);

        AuditLogger::logReadinessReportViewed($product, request()->user());

        return Inertia::render('products/readiness/Show', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'report' => $report,
        ]);
    }

    public function export(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $report = $this->readiness->build($product);

        AuditLogger::logReadinessReportExported($product, request()->user());

        $filename = sprintf(
            'readiness-%s-%s.pdf',
            $product->slug,
            now()->format('Y-m-d'),
        );

        return Pdf::loadView('pdf.product-readiness', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'report' => $report,
        ])
            ->setPaper('a4')
            ->stream($filename);
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

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }
}

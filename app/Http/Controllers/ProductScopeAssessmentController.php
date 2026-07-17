<?php

namespace App\Http\Controllers;

use App\Enums\ScopeStatus;
use App\Http\Requests\PreviewProductScopeAssessmentRequest;
use App\Http\Requests\StoreProductScopeAssessmentRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ScopeAssessmentService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductScopeAssessmentController extends Controller
{
    public function __construct(
        private readonly ScopeAssessmentService $assessments,
    ) {}

    public function preview(PreviewProductScopeAssessmentRequest $request): JsonResponse
    {
        $evaluation = $this->assessments->evaluate($request->input('answers', []));

        return response()->json([
            'suggested_status' => $evaluation['suggested_status']->value,
            'rationale' => $evaluation['rationale'],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        return response()->json([
            'assessment' => $this->assessments->latestPayload($product->latestScopeAssessment()),
        ]);
    }

    public function store(
        StoreProductScopeAssessmentRequest $request,
        Product $product,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $this->assessments->storeAndApply(
            $product,
            $request->input('answers', []),
            ScopeStatus::from($request->string('final_status')->toString()),
            $request->input('rationale'),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.scope_wizard.saved'),
        ]);

        return redirect()->route('products.edit', $product);
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

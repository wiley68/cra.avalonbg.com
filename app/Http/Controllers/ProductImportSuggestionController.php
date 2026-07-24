<?php

namespace App\Http\Controllers;

use App\Models\ImportSuggestion;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ImportSuggestionService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductImportSuggestionController extends Controller
{
    public function __construct(
        private readonly ImportSuggestionService $suggestions,
    ) {
    }

    public function accept(Product $product, ImportSuggestion $suggestion): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertSuggestionBelongsToProduct($product, $suggestion);
        $this->authorize('update', [$product, $organization]);

        $this->suggestions->accept($suggestion, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.suggestions.accepted'),
        ]);

        return back();
    }

    public function dismiss(Product $product, ImportSuggestion $suggestion): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertSuggestionBelongsToProduct($product, $suggestion);
        $this->authorize('update', [$product, $organization]);

        $this->suggestions->dismiss($suggestion, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.suggestions.dismissed'),
        ]);

        return back();
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

    private function assertSuggestionBelongsToProduct(Product $product, ImportSuggestion $suggestion): void
    {
        if ($suggestion->product_id !== $product->id) {
            abort(404);
        }
    }
}

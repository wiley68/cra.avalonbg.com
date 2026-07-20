<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Product;
use App\Models\VcsImportSuggestion;
use App\Services\VcsImportSuggestionService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductVcsImportSuggestionController extends Controller
{
    public function __construct(
        private readonly VcsImportSuggestionService $suggestions,
    ) {
    }

    public function accept(Product $product, VcsImportSuggestion $suggestion): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertSuggestionBelongsToProduct($product, $suggestion);
        $this->authorize('update', [$product, $organization]);

        $this->suggestions->accept($suggestion, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.repository.suggestions.accepted'),
        ]);

        return back();
    }

    public function dismiss(Product $product, VcsImportSuggestion $suggestion): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertSuggestionBelongsToProduct($product, $suggestion);
        $this->authorize('update', [$product, $organization]);

        $this->suggestions->dismiss($suggestion, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.repository.suggestions.dismissed'),
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

    private function assertSuggestionBelongsToProduct(Product $product, VcsImportSuggestion $suggestion): void
    {
        if ($suggestion->product_id !== $product->id) {
            abort(404);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVulnerability;
use App\Models\VcsImportSuggestion;
use App\Services\AiAssistantService;
use App\Services\VcsImportSuggestionService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductVcsImportSuggestionController extends Controller
{
    public function __construct(
        private readonly VcsImportSuggestionService $suggestions,
        private readonly AiAssistantService $assistant,
    ) {
    }

    public function accept(Product $product, VcsImportSuggestion $suggestion): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertSuggestionBelongsToProduct($product, $suggestion);
        $this->authorize('update', [$product, $organization]);

        $entity = $this->suggestions->accept($suggestion, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $entity instanceof ProductVulnerability
                ? Translations::get('products.repository.suggestions.accepted_vulnerability')
                : Translations::get('products.repository.suggestions.accepted'),
        ]);

        if ($entity instanceof ProductVulnerability) {
            return redirect()->route('products.vulnerabilities.edit', [
                'product' => $product,
                'vulnerability' => $entity,
            ]);
        }

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

    public function suggestAiTriage(
        Request $request,
        Product $product,
        VcsImportSuggestion $suggestion,
    ): JsonResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertSuggestionBelongsToProduct($product, $suggestion);
        $this->authorize('update', [$product, $organization]);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->assistant->suggestImportedFindingTriageSummary(
            $product,
            $suggestion,
            $request->user(),
            $validated['note'] ?? null,
            $organization->resolvedLocale(),
        );

        return response()->json([
            'summary_markdown' => $result['draft']['summary_markdown'],
            'suggested_severity' => $result['draft']['suggested_severity'],
            'human_review_required' => true,
            'disclaimer' => $result['draft']['disclaimer'],
            'provider' => $result['provider'],
            'model' => $result['model'],
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

    private function assertSuggestionBelongsToProduct(Product $product, VcsImportSuggestion $suggestion): void
    {
        if ($suggestion->product_id !== $product->id) {
            abort(404);
        }
    }
}

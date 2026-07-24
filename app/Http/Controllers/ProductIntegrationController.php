<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Http\Requests\StoreProductJiraLinkRequest;
use App\Jobs\SyncProductIntegrationJob;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ProductIntegrationLinkService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductIntegrationController extends Controller
{
    public function __construct(
        private readonly ProductIntegrationLinkService $links,
    ) {
    }

    public function update(StoreProductJiraLinkRequest $request, Product $product, string $provider): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertJiraProvider($provider);

        $this->links->linkJiraProject(
            product: $product,
            projectKey: $request->string('project_key')->toString(),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.jira.linked'),
        ]);

        return back();
    }

    public function destroy(Product $product, string $provider): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);
        $this->assertJiraProvider($provider);

        $link = $this->links->jiraLinkForProduct($product);

        if ($link === null) {
            abort(404);
        }

        $this->links->unlink($link, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.jira.unlinked'),
        ]);

        return back();
    }

    public function sync(Request $request, Product $product, string $provider): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);
        $this->assertJiraProvider($provider);

        $link = $this->links->jiraLinkForProduct($product);

        if ($link === null) {
            abort(404);
        }

        SyncProductIntegrationJob::dispatchSync($link->id, $request->user()->id);

        $run = IntegrationSyncRun::query()
            ->where('link_id', $link->id)
            ->latest('id')
            ->first();

        if ($run?->status === IntegrationSyncRunStatus::Failed) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => Translations::get('products.integrations.jira.sync_failed'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.jira.sync_succeeded'),
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

    private function assertJiraProvider(string $provider): void
    {
        if ($provider !== IntegrationProvider::Jira->value) {
            abort(404);
        }
    }
}

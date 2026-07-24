<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Http\Requests\StoreProductJiraLinkRequest;
use App\Http\Requests\StoreProductSnykLinkRequest;
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

    public function update(Request $request, Product $product, string $provider): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        return match ($provider) {
            IntegrationProvider::Jira->value => $this->updateJira($request, $product),
            IntegrationProvider::Snyk->value => $this->updateSnyk($request, $product),
            default => abort(404),
        };
    }

    public function destroy(Product $product, string $provider): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        $integrationProvider = $this->resolveProvider($provider);
        $link = $this->links->linkForProvider($product, $integrationProvider);

        if ($link === null) {
            abort(404);
        }

        $this->links->unlink($link, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get(
                $integrationProvider === IntegrationProvider::Snyk
                ? 'products.integrations.snyk.unlinked'
                : 'products.integrations.jira.unlinked',
            ),
        ]);

        return back();
    }

    public function sync(Request $request, Product $product, string $provider): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        $integrationProvider = $this->resolveProvider($provider);
        $link = $this->links->linkForProvider($product, $integrationProvider);

        if ($link === null) {
            abort(404);
        }

        SyncProductIntegrationJob::dispatchSync($link->id, $request->user()->id);

        $run = IntegrationSyncRun::query()
            ->where('link_id', $link->id)
            ->latest('id')
            ->first();

        $prefix = $integrationProvider === IntegrationProvider::Snyk
            ? 'products.integrations.snyk'
            : 'products.integrations.jira';

        if ($run?->status === IntegrationSyncRunStatus::Failed) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => Translations::get($prefix . '.sync_failed'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get($prefix . '.sync_succeeded'),
        ]);

        return back();
    }

    private function updateJira(Request $request, Product $product): RedirectResponse
    {
        /** @var StoreProductJiraLinkRequest $form */
        $form = app(StoreProductJiraLinkRequest::class);

        $this->links->linkJiraProject(
            product: $product,
            projectKey: $form->string('project_key')->toString(),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.jira.linked'),
        ]);

        return back();
    }

    private function updateSnyk(Request $request, Product $product): RedirectResponse
    {
        /** @var StoreProductSnykLinkRequest $form */
        $form = app(StoreProductSnykLinkRequest::class);

        $this->links->linkSnykTarget(
            product: $product,
            orgId: $form->string('org_id')->toString(),
            projectId: $form->string('project_id')->toString(),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.integrations.snyk.linked'),
        ]);

        return back();
    }

    private function resolveProvider(string $provider): IntegrationProvider
    {
        return match ($provider) {
            IntegrationProvider::Jira->value => IntegrationProvider::Jira,
            IntegrationProvider::Snyk->value => IntegrationProvider::Snyk,
            default => abort(404),
        };
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

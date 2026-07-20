<?php

namespace App\Http\Controllers;

use App\Enums\VcsProvider;
use App\Enums\VcsSyncRunStatus;
use App\Http\Requests\StoreProductRepositoryRequest;
use App\Jobs\SyncProductRepositoryJob;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\VcsSyncRun;
use App\Services\ProductRepositoryService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductRepositoryController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryService $repositories,
    ) {
    }

    public function store(StoreProductRepositoryRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $connection = OrganizationVcsConnection::query()->findOrFail(
            $request->integer('connection_id'),
        );

        $this->repositories->link(
            product: $product,
            connection: $connection,
            repositoryInput: $request->string('repository')->toString(),
            actor: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.repository.linked'),
        ]);

        return back();
    }

    public function sync(Request $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        $repository = $product->repository;

        if ($repository === null) {
            abort(404);
        }

        SyncProductRepositoryJob::dispatchSync($repository->id, $request->user()->id);

        $run = VcsSyncRun::query()
            ->where('repository_id', $repository->id)
            ->latest('id')
            ->first();

        if ($run?->status === VcsSyncRunStatus::Failed) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => Translations::get('products.repository.sync_failed'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.repository.sync_succeeded'),
        ]);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        $repository = $product->repository;

        if ($repository === null) {
            abort(404);
        }

        $this->repositories->unlink($repository, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.repository.unlinked'),
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

    /**
     * @return list<array{id: int, provider: string, label: string|null, status: string}>
     */
    public static function connectionOptions(Organization $organization): array
    {
        return OrganizationVcsConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', VcsProvider::Github)
            ->orderBy('label')
            ->get()
            ->map(fn(OrganizationVcsConnection $connection): array => [
                'id' => $connection->id,
                'provider' => $connection->provider->value,
                'label' => $connection->label,
                'status' => $connection->status->value,
            ])
            ->all();
    }
}

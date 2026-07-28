<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(
        private readonly ProductStorageCleanup $productStorage,
        private readonly BillingDocumentService $billingDocuments,
    ) {
    }

    /**
     * Permanently destroy an organization and its tenant data.
     *
     * Products, controls, evidence, tasks and membership pivots cascade via FKs.
     * Member user accounts that are not platform admins and have no remaining
     * organization memberships are deleted afterwards.
     *
     * @return array{deleted_users: int}
     */
    public function destroy(Organization $organization): array
    {
        return DB::transaction(function () use ($organization) {
            $memberIds = $organization->users()
                ->where('users.is_platform_admin', false)
                ->pluck('users.id')
                ->all();

            Product::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->each(fn(Product $product) => $this->productStorage->purge($product));

            $this->billingDocuments->deleteStorageForOrganization($organization);

            $organization->delete();

            $deletedUsers = 0;

            if ($memberIds !== []) {
                $deletedUsers = User::query()
                    ->whereIn('id', $memberIds)
                    ->where('is_platform_admin', false)
                    ->whereDoesntHave('organizations')
                    ->delete();
            }

            return ['deleted_users' => $deletedUsers];
        });
    }
}

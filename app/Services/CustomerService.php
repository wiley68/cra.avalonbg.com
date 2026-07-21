<?php

namespace App\Services;

use App\Enums\CustomerCriticality;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     name: string,
     *     external_ref: string|null,
     *     primary_contact: string|null,
     *     criticality: string,
     *     is_active: bool,
     *     deployments_count: int
     * }>
     */
    public function paginate(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'name',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = Customer::query()
            ->where('organization_id', $organization->id)
            ->withCount('deployments');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('external_ref', 'like', "%{$search}%")
                    ->orWhere('primary_contact', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('criticality', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'external_ref' => 'external_ref',
            'primary_contact' => 'primary_contact',
            'criticality' => 'criticality',
            'is_active' => 'is_active',
            default => 'name',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(Customer $customer) => $this->listItemPayload($customer));
    }

    /**
     * @param  array{
     *     name: string,
     *     external_ref?: string|null,
     *     primary_contact?: string|null,
     *     criticality: CustomerCriticality,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function create(Organization $organization, array $attributes, User $actor): Customer
    {
        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'name' => $attributes['name'],
            'external_ref' => $attributes['external_ref'] ?? null,
            'primary_contact' => $attributes['primary_contact'] ?? null,
            'criticality' => $attributes['criticality'],
            'notes' => $attributes['notes'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ]);

        AuditLogger::logCustomerCreated($customer, $actor);

        return $customer;
    }

    /**
     * @param  array{
     *     name: string,
     *     external_ref?: string|null,
     *     primary_contact?: string|null,
     *     criticality: CustomerCriticality,
     *     notes?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function update(Customer $customer, array $attributes, User $actor): Customer
    {
        $customer->update([
            'name' => $attributes['name'],
            'external_ref' => $attributes['external_ref'] ?? null,
            'primary_contact' => $attributes['primary_contact'] ?? null,
            'criticality' => $attributes['criticality'],
            'notes' => $attributes['notes'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ]);

        $fresh = $customer->fresh();
        AuditLogger::logCustomerUpdated($fresh, $actor);

        return $fresh;
    }

    public function delete(Customer $customer, User $actor): void
    {
        AuditLogger::logCustomerDeleted($customer, $actor);
        $customer->delete();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     external_ref: string|null,
     *     primary_contact: string|null,
     *     criticality: string,
     *     is_active: bool,
     *     deployments_count: int
     * }
     */
    private function listItemPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'external_ref' => $customer->external_ref,
            'primary_contact' => $customer->primary_contact,
            'criticality' => $customer->criticality->value,
            'is_active' => $customer->is_active,
            'deployments_count' => (int) ($customer->deployments_count ?? 0),
        ];
    }
}

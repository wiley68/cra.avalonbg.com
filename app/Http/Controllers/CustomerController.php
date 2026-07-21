<?php

namespace App\Http\Controllers;

use App\Enums\CustomerCriticality;
use App\Http\Requests\ImportCustomersCsvRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Organization;
use App\Services\CustomerCsvImportService;
use App\Services\CustomerService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly CustomerCsvImportService $imports,
    ) {
    }

    public function index(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [Customer::class, $organization]);

        return Inertia::render('customers/Index', [
            'organization' => $this->organizationPayload($organization),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function create(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [Customer::class, $organization]);

        return Inertia::render('customers/Create', [
            'organization' => $this->organizationPayload($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

        $customer = $this->customers->create(
            $organization,
            [
                'name' => $request->string('name')->toString(),
                'external_ref' => $request->input('external_ref'),
                'primary_contact' => $request->input('primary_contact'),
                'criticality' => CustomerCriticality::from($request->string('criticality')->toString()),
                'notes' => $request->input('notes'),
                'is_active' => $request->boolean('is_active', true),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('customers.created'),
        ]);

        return redirect()->route('customers.edit', $customer);
    }

    public function edit(Customer $customer): Response
    {
        $organization = $this->currentOrganization();
        $this->assertCustomerInOrganization($customer, $organization);
        $this->authorize('view', [$customer, $organization]);

        return Inertia::render('customers/Edit', [
            'organization' => $this->organizationPayload($organization),
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'external_ref' => $customer->external_ref,
                'primary_contact' => $customer->primary_contact,
                'criticality' => $customer->criticality->value,
                'notes' => $customer->notes,
                'is_active' => $customer->is_active,
            ],
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertCustomerInOrganization($customer, $organization);

        $this->customers->update(
            $customer,
            [
                'name' => $request->string('name')->toString(),
                'external_ref' => $request->input('external_ref'),
                'primary_contact' => $request->input('primary_contact'),
                'criticality' => CustomerCriticality::from($request->string('criticality')->toString()),
                'notes' => $request->input('notes'),
                'is_active' => $request->boolean('is_active', true),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('customers.updated'),
        ]);

        return redirect()->route('customers.edit', $customer);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertCustomerInOrganization($customer, $organization);
        $this->authorize('delete', [$customer, $organization]);

        $this->customers->delete($customer, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('customers.deleted'),
        ]);

        return redirect()->route('customers.index');
    }

    public function importForm(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [Customer::class, $organization]);

        return Inertia::render('customers/Import', [
            'organization' => $this->organizationPayload($organization),
        ]);
    }

    public function import(ImportCustomersCsvRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

        $result = $this->imports->import(
            $organization,
            $request->file('file'),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('customers.import.completed', [
                'created' => (string) $result['created'],
                'updated' => (string) $result['updated'],
                'skipped' => (string) $result['skipped'],
                'errors' => (string) count($result['errors']),
            ]),
        ]);

        if ($result['errors'] !== []) {
            Inertia::flash('import_errors', $result['errors']);
        }

        return redirect()->route('customers.index');
    }

    public function importTemplate(): StreamedResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [Customer::class, $organization]);

        $headers = [
            'name',
            'external_ref',
            'primary_contact',
            'criticality',
            'notes',
            'is_active',
        ];

        return response()->streamDownload(function () use ($headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, [
                'Acme Corp',
                'CRM-1001',
                'ops@acme.example',
                'high',
                'Tier-1 customer',
                '1',
            ]);
            fclose($handle);
        }, 'customers-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

    private function assertCustomerInOrganization(Customer $customer, Organization $organization): void
    {
        if ($customer->organization_id !== $organization->id) {
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
     * @return array{criticalities: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'criticalities' => array_column(CustomerCriticality::cases(), 'value'),
        ];
    }
}

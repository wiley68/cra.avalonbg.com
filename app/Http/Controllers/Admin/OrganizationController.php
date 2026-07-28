<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingDocumentType;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleSlug;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Services\BankPaymentService;
use App\Services\BillingDocumentService;
use App\Services\ControlService;
use App\Services\OrganizationMembershipService;
use App\Services\OrganizationService;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $memberships,
        private readonly ControlService $controls,
        private readonly OrganizationService $organizations,
        private readonly BankPaymentService $bankPayments,
        private readonly BillingDocumentService $billingDocuments,
    ) {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Organization::class);

        return Inertia::render('admin/organizations/Index');
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('admin/organizations/Create', [
            'subscriptionPlans' => SubscriptionPlan::catalogPayload(),
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = DB::transaction(function () use ($request) {
            $organization = Organization::query()->create([
                'name' => $request->string('name'),
                'slug' => $request->string('slug'),
                'billing_email' => $request->input('billing_email'),
                'subscription_plan' => $request->input('subscription_plan') ?: SubscriptionPlan::Free->value,
                'billing_status' => BillingStatus::Active->value,
                'billing_interval' => null,
                'payment_method' => PaymentMethod::AdminComp->value,
                'billing_activated_at' => now(),
                'is_active' => $request->boolean('is_active', true),
                'locale' => $request->string('locale')->toString(),
            ]);

            if ($request->boolean('create_owner')) {
                $ownerRoleId = Role::query()
                    ->where('slug', RoleSlug::OrganizationOwner->value)
                    ->value('id');

                if ($ownerRoleId === null) {
                    abort(500, 'Organization owner role is missing.');
                }

                $this->memberships->createAndAttach(
                    $organization,
                    [
                        'name' => $request->string('owner_name')->toString(),
                        'email' => $request->string('owner_email')->toString(),
                        'password' => $request->string('owner_password')->toString(),
                        'must_change_password' => true,
                    ],
                    (int) $ownerRoleId,
                    $request->user(),
                );
            }

            if ($request->boolean('seed_starter_controls', true)) {
                $this->controls->seedStarterCatalogue($organization, refreshExisting: false);
            }

            return $organization;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.organizations.created'),
        ]);

        return redirect()->route('admin.organizations.edit', $organization);
    }

    public function edit(Organization $organization): Response
    {
        $this->authorize('update', $organization);

        return Inertia::render('admin/organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'billing_email' => $organization->billing_email,
                'subscription_plan' => $organization->subscription_plan
                    ?: $organization->resolvedSubscriptionPlan()->value,
                'is_active' => (bool) $organization->is_active,
                'locale' => $organization->resolvedLocale(),
                'users_count' => $organization->users()->count(),
            ],
            'subscriptionPlans' => SubscriptionPlan::catalogPayload(),
        ]);
    }

    public function billing(Organization $organization): Response
    {
        $this->authorize('update', $organization);

        $pending = $this->bankPayments->pendingRequest($organization);

        return Inertia::render('admin/organizations/Billing', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'billing_email' => $organization->billing_email,
                'subscription_plan' => $organization->subscription_plan
                    ?: $organization->resolvedSubscriptionPlan()->value,
                'billing_status' => $organization->resolvedBillingStatus()->value,
                'billing_interval' => $organization->billing_interval?->value,
                'payment_method' => $organization->payment_method?->value,
                'billing_activated_at' => $organization->billing_activated_at?->toIso8601String(),
            ],
            'pendingBankPayment' => $this->bankPayments->requestPayload($pending),
            'canActivateBilling' => !$organization->isBillingActive(),
            'billingDocuments' => $this->billingDocuments->listPayload($organization),
            'documentRecipientEmail' => $this->billingDocuments->resolveRecipientEmail($organization),
            'documentTypes' => BillingDocumentType::values(),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $previousLocale = $organization->resolvedLocale();
        $previousPlan = $organization->resolvedSubscriptionPlan()->value;
        $locale = $request->string('locale')->toString();
        $nextPlan = (string) $request->input('subscription_plan');

        $organization->update([
            'name' => $request->string('name'),
            'slug' => $request->string('slug'),
            'billing_email' => $request->input('billing_email'),
            'subscription_plan' => $nextPlan,
            'is_active' => $request->boolean('is_active'),
            'locale' => $locale,
        ]);

        AuditLogger::logSubscriptionPlanChanged(
            $organization->fresh() ?? $organization,
            $previousPlan,
            $nextPlan,
            $request->user(),
            'admin',
        );

        if ($previousLocale !== $locale) {
            $this->controls->seedStarterCatalogue($organization->fresh(), refreshExisting: true);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.organizations.updated'),
        ]);

        return redirect()->route('admin.organizations.edit', $organization);
    }

    public function activateBilling(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $this->bankPayments->activate(
            $organization,
            $request->user(),
            $this->bankPayments->pendingRequest($organization),
            PaymentMethod::Bank,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.organizations.billing_activated'),
        ]);

        return redirect()->route('admin.organizations.billing', $organization);
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $this->organizations->destroy($organization);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.organizations.deleted'),
        ]);

        return redirect()->route('admin.organizations.index');
    }
}

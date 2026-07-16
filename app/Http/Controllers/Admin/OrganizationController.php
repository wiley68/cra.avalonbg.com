<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Services\OrganizationMembershipService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $memberships,
    ) {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active', 'billing_email', 'subscription_plan', 'created_at'])
            ->map(fn(Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'is_active' => (bool) $organization->is_active,
                'billing_email' => $organization->billing_email,
                'subscription_plan' => $organization->subscription_plan,
                'users_count' => $organization->users_count,
                'created_at' => $organization->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Organization::class);

        return Inertia::render('admin/organizations/Create');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = DB::transaction(function () use ($request) {
            $organization = Organization::query()->create([
                'name' => $request->string('name'),
                'slug' => $request->string('slug'),
                'billing_email' => $request->input('billing_email'),
                'subscription_plan' => $request->input('subscription_plan'),
                'is_active' => $request->boolean('is_active', true),
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
                'subscription_plan' => $organization->subscription_plan,
                'is_active' => (bool) $organization->is_active,
                'users_count' => $organization->users()->count(),
            ],
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            'name' => $request->string('name'),
            'slug' => $request->string('slug'),
            'billing_email' => $request->input('billing_email'),
            'subscription_plan' => $request->input('subscription_plan'),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.organizations.updated'),
        ]);

        return redirect()->route('admin.organizations.edit', $organization);
    }
}

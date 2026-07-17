<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationUserRequest;
use App\Http\Requests\Admin\UpdateOrganizationUserRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationUserController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $memberships,
    ) {
    }

    public function index(Organization $organization): Response
    {
        $this->authorize('viewAny', [User::class, $organization]);

        return Inertia::render('admin/organizations/users/Index', [
            'organization' => $this->organizationPayload($organization),
        ]);
    }

    public function create(Organization $organization): Response
    {
        $this->authorize('create', [User::class, $organization]);

        return Inertia::render('admin/organizations/users/Create', [
            'organization' => $this->organizationPayload($organization),
            'roles' => $this->memberships->organizationRoles(),
        ]);
    }

    public function store(StoreOrganizationUserRequest $request, Organization $organization): RedirectResponse
    {
        $this->memberships->createAndAttach(
            $organization,
            [
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
                'must_change_password' => $request->boolean('must_change_password', true),
            ],
            $request->integer('role_id'),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.users.created'),
        ]);

        return redirect()->route('admin.organizations.users.index', $organization);
    }

    public function edit(Organization $organization, User $user): Response
    {
        $this->memberships->assertMembership($organization, $user);
        $this->authorize('update', [$user, $organization]);

        $pivot = $user->organizations()
            ->where('organizations.id', $organization->id)
            ->firstOrFail()
            ->pivot;

        return Inertia::render('admin/organizations/users/Edit', [
            'organization' => $this->organizationPayload($organization),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'must_change_password' => (bool) $user->must_change_password,
                'role_id' => (int) $pivot->role_id,
            ],
            'roles' => $this->memberships->organizationRoles(),
        ]);
    }

    public function update(
        UpdateOrganizationUserRequest $request,
        Organization $organization,
        User $user,
    ): RedirectResponse {
        $this->memberships->assertMembership($organization, $user);

        DB::transaction(function () use ($request, $organization, $user) {
            $user->update([
                'name' => $request->string('name'),
                'email' => $request->string('email'),
                'must_change_password' => $request->boolean('must_change_password'),
            ]);

            $this->memberships->updateRole(
                $organization,
                $user,
                $request->integer('role_id'),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.users.updated'),
        ]);

        return redirect()->route('admin.organizations.users.index', $organization);
    }

    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        $this->memberships->assertMembership($organization, $user);
        $this->authorize('delete', [$user, $organization]);

        $this->memberships->deleteMember($organization, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('admin.users.deleted'),
        ]);

        return redirect()->route('admin.organizations.users.index', $organization);
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
}

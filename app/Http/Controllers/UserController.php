<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $memberships,
    ) {
    }

    public function index(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [User::class, $organization]);

        return Inertia::render('users/Index', [
            'organization' => $this->organizationPayload($organization),
        ]);
    }

    public function create(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [User::class, $organization]);

        return Inertia::render('users/Create', [
            'organization' => $this->organizationPayload($organization),
            'roles' => $this->memberships->organizationRoles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

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
            'message' => Translations::get('users.created'),
        ]);

        return redirect()->route('users.index');
    }

    public function edit(User $user): Response
    {
        $organization = $this->currentOrganization();
        $this->memberships->assertMembership($organization, $user);
        $this->authorize('update', [$user, $organization]);

        $pivot = $user->organizations()
            ->where('organizations.id', $organization->id)
            ->firstOrFail()
            ->pivot;

        return Inertia::render('users/Edit', [
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

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $organization = $this->currentOrganization();
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
            'message' => Translations::get('users.updated'),
        ]);

        return redirect()->route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->memberships->assertMembership($organization, $user);
        $this->authorize('delete', [$user, $organization]);

        $this->memberships->deleteMember($organization, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('users.deleted'),
        ]);

        return redirect()->route('users.index');
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $organization = Organization::query()->firstOrFail();
        $roleMap = Role::query()->pluck('name', 'id');

        $users = $organization->users()
            ->select('users.id', 'users.name', 'users.email', 'users.must_change_password', 'users.is_system_admin')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'must_change_password' => (bool) $user->must_change_password,
                'is_system_admin' => (bool) $user->is_system_admin,
                'role_id' => $user->pivot->role_id,
                'role_name' => $roleMap[$user->pivot->role_id] ?? 'Unknown',
            ])
            ->values();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'roles' => Role::query()
                ->where('scope', 'organization')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $organization = Organization::query()->firstOrFail();

        $user = User::query()->create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => $request->string('password'),
            'must_change_password' => $request->boolean('must_change_password', true),
            'is_system_admin' => false,
        ]);

        $organization->users()->attach($user->id, [
            'role_id' => $request->integer('role_id'),
            'invited_by' => $request->user()->id,
            'joined_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return redirect()->route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        $organization = Organization::query()->firstOrFail();
        $pivot = $user->organizations()
            ->where('organizations.id', $organization->id)
            ->firstOrFail()
            ->pivot;

        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'must_change_password' => (bool) $user->must_change_password,
                'is_system_admin' => (bool) $user->is_system_admin,
                'role_id' => $pivot->role_id,
            ],
            'roles' => Role::query()
                ->where('scope', 'organization')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $organization = Organization::query()->firstOrFail();

        $user->update([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'must_change_password' => $request->boolean('must_change_password'),
            'is_system_admin' => $request->boolean('is_system_admin'),
        ]);

        $organization->users()->updateExistingPivot($user->id, [
            'role_id' => $request->integer('role_id'),
            'updated_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return redirect()->route('admin.users.index');
    }
}


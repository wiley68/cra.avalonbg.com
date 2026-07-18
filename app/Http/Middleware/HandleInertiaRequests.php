<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use App\Support\Translations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $organization = null;

        if (Schema::hasTable('organizations') && $user) {
            $organization = $user->currentOrganization();
        }

        $role = $organization ? $user?->roleIn($organization) : null;
        $canManageUsers = $user !== null && $organization !== null
            ? $user->canManageUsers($organization)
            : false;
        $canViewProducts = $user !== null && $organization !== null
            ? $user->canViewProducts($organization)
            : false;
        $canManageProducts = $user !== null && $organization !== null
            ? $user->canManageProducts($organization)
            : false;
        $canViewRequirements = $user !== null && $organization !== null
            ? $user->canViewRequirements($organization)
            : false;
        $canManageRequirements = $user !== null && $organization !== null
            ? $user->canManageRequirements($organization)
            : false;
        $canViewControls = $user !== null && $organization !== null
            ? $user->canViewControls($organization)
            : false;
        $canManageControls = $user !== null && $organization !== null
            ? $user->canManageControls($organization)
            : false;
        $canViewRisks = $user !== null && $organization !== null
            ? $user->canViewRisks($organization)
            : false;
        $canManageRisks = $user !== null && $organization !== null
            ? $user->canManageRisks($organization)
            : false;
        $canManageOrganizations = $user?->canManageOrganizations() ?? false;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'version' => (string) config('app.version'),
            'locale' => app()->getLocale(),
            'locales' => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'bg', 'label' => 'Български'],
            ],
            'translations' => Translations::forLocale(),
            'appearance' => $this->resolveAppearance($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
                    'is_platform_admin' => $user->isPlatformAdmin(),
                    'must_change_password' => (bool) $user->must_change_password,
                    'permissions' => $user->resolvedPermissions($organization),
                    'role' => $user->isPlatformAdmin()
                        ? 'platform_admin'
                        : $role?->slug,
                    'role_label' => $user->isPlatformAdmin()
                        ? null
                        : $role?->name,
                    'can_manage_users' => $canManageUsers,
                    'can_view_products' => $canViewProducts,
                    'can_manage_products' => $canManageProducts,
                    'can_view_requirements' => $canViewRequirements,
                    'can_manage_requirements' => $canManageRequirements,
                    'can_view_controls' => $canViewControls,
                    'can_manage_controls' => $canManageControls,
                    'can_view_risks' => $canViewRisks,
                    'can_manage_risks' => $canManageRisks,
                    'can_manage_organizations' => $canManageOrganizations,
                ] : null,
            ],
            'organization' => $organization?->only(['id', 'name', 'slug']),
            'sidebarOpen' => !$request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function resolveAppearance(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return $user->appearance ?? Appearance::System->value;
        }

        $cookieAppearance = $request->cookie('appearance');

        return Appearance::tryFrom((string) $cookieAppearance) !== null
            ? (string) $cookieAppearance
            : Appearance::System->value;
    }
}

<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use App\Models\Organization;
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

        if (Schema::hasTable('organizations')) {
            $organization = $user
                ? $user->organizations()->select('organizations.id', 'organizations.name', 'organizations.slug')->first()
                : Organization::query()->select(['id', 'name', 'slug'])->first();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
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
                    'is_system_admin' => $user->isSystemAdmin(),
                    'must_change_password' => (bool) $user->must_change_password,
                    'permissions' => $user->resolvedPermissions($organization),
                    'role' => $organization ? $user->roleIn($organization)?->slug : null,
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

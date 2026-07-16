<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use App\Http\Middleware\ForceHttps;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureUrlForReverseProxy();
        $this->configureHttpsOnly();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::before(fn(User $user) => $user->isSystemAdmin() ? true : null);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
    }

    /**
     * Behind Cloudflare / nginx / tunnel: клиентът е по HTTPS, а APP_URL може да е http://localhost.
     * Генерираните URL-и и схемата да следват реалния протокол.
     */
    protected function configureUrlForReverseProxy(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');

            return;
        }

        if (request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }
    }

    /**
     * Redirect all non-HTTPS requests to HTTPS and force URL generation.
     *
     * Enable via env('APP_FORCE_HTTPS', false).
     */
    protected function configureHttpsOnly(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $forceHttps = filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);
        if (!$forceHttps) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        if (!$kernel instanceof HttpKernel) {
            return;
        }

        $kernel->pushMiddleware(ForceHttps::class);
    }
}

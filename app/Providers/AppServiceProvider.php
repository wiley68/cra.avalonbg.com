<?php

namespace App\Providers;

use App\Enums\PermissionSlug;
use App\Http\Middleware\ForceHttps;
use App\Models\AuditLog;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductRequirement;
use App\Models\ProductRisk;
use App\Models\ProductComponent;
use App\Models\ProductVulnerability;
use App\Models\Evidence;
use App\Models\Requirement;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\ControlPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\ProductComponentPolicy;
use App\Policies\ProductControlPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductRequirementPolicy;
use App\Policies\ProductRiskPolicy;
use App\Policies\ProductVulnerabilityPolicy;
use App\Policies\EvidencePolicy;
use App\Policies\RequirementPolicy;
use App\Policies\UserPolicy;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;

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
        $this->configureAuditLogging();
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
            fn(): Password => Password::min(9)
                ->mixedCase()
                ->numbers()
                ->symbols(),
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::define(
            'platform.admin',
            fn(User $user) => $user->isPlatformAdmin()
            || $user->hasPermission(PermissionSlug::PlatformAdmin->value),
        );
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Requirement::class, RequirementPolicy::class);
        Gate::policy(ProductRequirement::class, ProductRequirementPolicy::class);
        Gate::policy(Control::class, ControlPolicy::class);
        Gate::policy(ProductControl::class, ProductControlPolicy::class);
        Gate::policy(ProductRisk::class, ProductRiskPolicy::class);
        Gate::policy(ProductComponent::class, ProductComponentPolicy::class);
        Gate::policy(ProductVulnerability::class, ProductVulnerabilityPolicy::class);
        Gate::policy(Evidence::class, EvidencePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
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

    protected function configureAuditLogging(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard !== 'web' || !$event->user instanceof User) {
                return;
            }

            AuditLogger::logLoginSuccess($event->user);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->guard !== 'web') {
                return;
            }

            $email = (string) ($event->credentials[Fortify::username()] ?? $event->credentials['email'] ?? '—');
            $user = $event->user instanceof User ? $event->user : null;

            AuditLogger::logLoginFailed($email, 'invalid_credentials', $user);
        });

        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function (ValidTwoFactorAuthenticationCodeProvided $event): void {
            if ($event->user instanceof User) {
                AuditLogger::logTwoFactorChallengeSuccess($event->user);
            }
        });

        Event::listen(TwoFactorAuthenticationFailed::class, function (TwoFactorAuthenticationFailed $event): void {
            if ($event->user instanceof User) {
                AuditLogger::logTwoFactorChallengeFailed($event->user);
            }
        });
    }
}

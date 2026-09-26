<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
        $this->removeFrameworkDefaultIdentityProvider();
    }

    /**
     * The `users` registry is a normal model, NEVER an auth provider (ADR 03):
     * the app keeps its 3 guards/providers (mahasiswa/dosen/admin, in
     * config/auth.php). Laravel still deep-merges the framework's default
     * `web` guard + `users` provider into auth config, which would make the
     * registry a login path — strip both so the registry cannot be used to
     * authenticate.
     */
    protected function removeFrameworkDefaultIdentityProvider(): void
    {
        $guards = config('auth.guards', []);
        $providers = config('auth.providers', []);

        unset($guards['web'], $providers['users']);

        config([
            'auth.guards' => $guards,
            'auth.providers' => $providers,
        ]);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}

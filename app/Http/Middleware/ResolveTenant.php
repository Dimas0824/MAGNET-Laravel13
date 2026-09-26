<?php

namespace App\Http\Middleware;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bind the tenant for the current request into the container as
 * `currentTenant`, so BelongsToTenant's global scope is deterministic per
 * request rather than depending on ambient container state.
 *
 * Today there is a single tenant, so this resolves the default tenant. The
 * binding is the seam a future per-request resolver (subdomain, session,
 * authenticated user) will override without touching the models.
 */
class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        BelongsToTenant::forgetResolvedTenant();

        $tenant = Tenant::query()->where('slug', 'default')->first();

        if ($tenant !== null) {
            // Bind the acting tenant AND prime the shared memo, so the global
            // scope never issues a second `tenants` lookup this request.
            app()->instance('currentTenant', $tenant);
            app()->instance('resolvedDefaultTenant', $tenant);
        }

        return $next($request);
    }
}

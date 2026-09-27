<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant scoping for ROOT models (ADR 03): stamp the current tenant on create
 * and scope every query to it.
 *
 * The "current tenant" resolves lazily:
 *   1. an explicitly bound instance `app('currentTenant')` (set by the request
 *      middleware, or restored by a queued listener from its payload),
 *   2. otherwise the default tenant row.
 *
 * SCOPE SEMANTICS (post P1-T7 contract):
 *   `tenant_id` is NOT NULL on every root table, so a row can never be
 *   un-attributed. The scope is therefore STRICT — `WHERE tenant_id = X` — for
 *   every tenant, with no NULL branch. The old expand-phase NULL-inclusive
 *   rule was explicitly temporary ("until the NOT NULL contract lands") and is
 *   now dead code that would be unsafe in a real multi-tenant deployment.
 *
 * When no tenant row exists at all (fresh DB during migration) the trait is a
 * no-op, so it never breaks existing behaviour or seeding.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $tenant = static::currentTenant();

                if ($tenant !== null) {
                    $model->setAttribute('tenant_id', $tenant->id);
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenant = static::currentTenant();

            if ($tenant === null) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenant->id);
        });
    }

    /**
     * Resolve the tenant this process is acting as, or null when none exists.
     */
    public static function currentTenant(): ?Tenant
    {
        if (app()->bound('currentTenant')) {
            return app('currentTenant');
        }

        return static::defaultTenant();
    }

    /**
     * The registry's default tenant, memoized ONCE per lifecycle in the
     * container. A trait static would be copied per using class, so each root
     * model would re-query `tenants`; the container binding is shared.
     */
    protected static function defaultTenant(): ?Tenant
    {
        if (! app()->bound('resolvedDefaultTenant')) {
            app()->instance('resolvedDefaultTenant', Tenant::query()->where('slug', 'default')->first());
        }

        return app('resolvedDefaultTenant');
    }

    /**
     * The default tenant's id, or null when the registry has none yet. Used by
     * callers that bypass the `creating` hook (e.g. `withoutEvents`) and must
     * stamp `tenant_id` explicitly now that the column is NOT NULL.
     */
    public static function defaultTenantId(): ?int
    {
        return static::defaultTenant()?->id;
    }

    /**
     * Forget the memoized default tenant so it is re-resolved on next use.
     * Called at the start of each request (middleware) and between tests.
     */
    public static function forgetResolvedTenant(): void
    {
        app()->forgetInstance('resolvedDefaultTenant');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

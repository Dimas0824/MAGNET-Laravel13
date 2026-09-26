<?php

use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Tenant;
use Database\Seeders\TenantBackfillSeeder;
use Database\Seeders\TenantSeeder;

/**
 * BelongsToTenant: root models are (a) stamped with the default tenant on
 * create, and (b) scoped so a row from another tenant is invisible.
 *
 * The trait resolves the current tenant lazily: an explicit bound tenant if
 * present, otherwise the default tenant row.
 */
beforeEach(function () {
    seedMasterData();
    (new TenantSeeder)->run();
});

it('stamps the default tenant on create when none is given', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $default = Tenant::where('slug', 'default')->sole();

    expect($mahasiswa->tenant_id)->toBe($default->id);
});

it('respects an explicitly provided tenant_id', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZY']);

    $mahasiswa = Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    expect($mahasiswa->tenant_id)->toBe($other->id);
});

it('scopes root model queries to the current tenant', function () {
    $default = Tenant::where('slug', 'default')->sole();
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZX']);

    Mahasiswa::factory()->create(['tenant_id' => $default->id]);
    Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    // Bind the "other" tenant; only its row should be visible.
    app()->instance('currentTenant', $other);
    $visible = Mahasiswa::query()->pluck('tenant_id')->unique()->values()->all();

    expect($visible)->toBe([$other->id]);
});

it('cannot persist an un-attributed (NULL tenant_id) root row after the contract', function () {
    // Post P1-T7 the column is NOT NULL, so an un-attributed row is impossible:
    // bypassing the creating hook to insert NULL must be rejected by the schema.
    expect(fn () => Mahasiswa::withoutEvents(
        fn () => Mahasiswa::forceCreate(Mahasiswa::factory()->make(['tenant_id' => null])->getAttributes())
    ))->toThrow(\Illuminate\Database\QueryException::class);
});

it('scopes strictly (no NULL branch) when the DEFAULT tenant is bound', function () {
    $default = Tenant::where('slug', 'default')->sole();
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZW']);

    Mahasiswa::factory()->create(['tenant_id' => $default->id]);
    $foreign = Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    // Even acting as the default tenant, another tenant's row must be invisible
    // (no NULL-inclusive leak, no cross-tenant leak).
    app()->instance('currentTenant', $default);
    $visible = Mahasiswa::query()->pluck('id');

    expect($visible->contains($foreign->id))->toBeFalse()
        ->and($visible->contains(Mahasiswa::query()->withoutGlobalScope('tenant')->where('tenant_id', $default->id)->value('id')))->toBeTrue();
});

it('is strict (excludes other tenants) when a non-default tenant is bound', function () {
    $default = Tenant::where('slug', 'default')->sole();
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZV']);

    $defaultRow = Mahasiswa::factory()->create(['tenant_id' => $default->id]);
    $otherRow = Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    app()->instance('currentTenant', $other);
    $visible = Mahasiswa::query()->pluck('id');

    expect($visible->contains($otherRow->id))->toBeTrue()
        ->and($visible->contains($defaultRow->id))->toBeFalse();
});

it('resolves the default tenant only once per request', function () {
    (new TenantSeeder)->run();

    // Prime + repeat several scoped queries; the tenants table must be hit once.
    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();

    Mahasiswa::query()->count();
    Mahasiswa::query()->count();
    \App\Models\LowonganMagang::query()->count();

    $tenantQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'from `tenants`'))
        ->count();

    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($tenantQueries)->toBeLessThanOrEqual(1);
});

it('resolves the default tenant once per request across DIFFERENT root models', function () {
    (new TenantSeeder)->run();
    \App\Models\Concerns\BelongsToTenant::forgetResolvedTenant();

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();

    // Trait statics are per-class; the memo must be shared or each model class
    // re-queries `tenants` (blowing query budgets).
    Mahasiswa::query()->count();
    \App\Models\LowonganMagang::query()->count();
    \App\Models\Perusahaan::query()->count();

    $tenantQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'from `tenants`'))
        ->count();

    \Illuminate\Support\Facades\DB::disableQueryLog();

    expect($tenantQueries)->toBeLessThanOrEqual(1);
});

it('applies the trait to every existing root model', function () {
    $rootModels = [
        Mahasiswa::class,
        \App\Models\DosenPembimbing::class,
        \App\Models\Admin::class,
        \App\Models\BidangIndustri::class,
        \App\Models\Pekerjaan::class,
        \App\Models\LokasiMagang::class,
        \App\Models\Perusahaan::class,
        LowonganMagang::class,
        \App\Models\BerkasPengajuanMagang::class,
        \App\Models\KontrakMagang::class,
        \App\Models\RecommendationRun::class,
    ];

    foreach ($rootModels as $model) {
        expect(in_array(\App\Models\Concerns\BelongsToTenant::class, class_uses_recursive($model), true))
            ->toBeTrue("{$model} does not use BelongsToTenant");
    }
});

<?php

use App\Models\Concerns\BelongsToTenant;
use App\Models\Mahasiswa;
use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Support\Facades\Route;

/**
 * ResolveTenant middleware: every web request must bind a tenant into the
 * container as `currentTenant` BEFORE any scoped model is queried, so the
 * tenant scope is deterministic per request (never "whatever happens to be in
 * the container"). With a single tenant today it resolves the default tenant;
 * the binding is what a future multi-tenant resolver would override.
 */
beforeEach(function () {
    seedMasterData();
    (new TenantSeeder)->run();
});

it('binds the default tenant as currentTenant for a web request', function () {
    $default = Tenant::where('slug', 'default')->sole();

    Route::middleware('web')->get('/_probe/tenant', function () {
        return response()->json([
            'bound' => app()->bound('currentTenant'),
            'id' => app()->bound('currentTenant') ? app('currentTenant')->id : null,
        ]);
    });

    $this->get('/_probe/tenant')
        ->assertOk()
        ->assertJson(['bound' => true, 'id' => $default->id]);
});

it('scopes root model queries to the resolved tenant inside a request', function () {
    $default = Tenant::where('slug', 'default')->sole();

    $mine = Mahasiswa::factory()->create(['tenant_id' => $default->id]);

    Route::middleware('web')->get('/_probe/count', function () {
        return response()->json(['count' => Mahasiswa::query()->count()]);
    });

    $this->get('/_probe/count')->assertOk()->assertJson(['count' => 1]);
});

it('renders a public page while the tenant middleware is active', function () {
    $this->get(route('guest.landing-page'))->assertOk();
});

it('does not bind a tenant when the registry has none', function () {
    // Post P1-T7 the tenant FK is ON DELETE RESTRICT, so a tenant that owns
    // root rows cannot be deleted (that is the point). To exercise the
    // "no tenant at all" branch, remove roots first, then the tenant.
    foreach (['berkas_pengajuan_magang', 'kontrak_magang', 'lowongan_magang', 'perusahaan', 'lokasi_magang', 'pekerjaan', 'bidang_industri', 'admin', 'dosen_pembimbing', 'mahasiswa', 'recommendation_run', 'users'] as $table) {
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            \Illuminate\Support\Facades\DB::table($table)->delete();
        }
    }

    Tenant::query()->delete();
    BelongsToTenant::forgetResolvedTenant();

    Route::middleware('web')->get('/_probe/none', function () {
        return response()->json(['bound' => app()->bound('currentTenant')]);
    });

    $this->get('/_probe/none')->assertOk()->assertJson(['bound' => false]);
});

it('rejects deleting a tenant that still owns root rows (RESTRICT)', function () {
    Mahasiswa::factory()->create();

    expect(fn () => Tenant::query()->delete())
        ->toThrow(\Illuminate\Database\QueryException::class);
});

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T1/T2: `users` becomes a REGISTRY of identities (one row per
 * mahasiswa/dosen/admin), not an auth provider. The 3 guards/providers in
 * config/auth.php stay untouched — a registry merely gives every identity a
 * stable, cross-role `users.id` that chats + audit can reference.
 *
 * T1 adds the registry columns (public_id); T2 backfills one row per auth row.
 */
it('adds a unique public_id registry column to users', function () {
    expect(Schema::hasColumn('users', 'public_id'))->toBeTrue();

    $type = DB::table('information_schema.columns')
        ->select('COLUMN_TYPE')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'users')
        ->where('COLUMN_NAME', 'public_id')
        ->value('COLUMN_TYPE');

    expect($type)->toBe('char(26)');

    $unique = collect(DB::select('SHOW INDEX FROM `users`'))
        ->filter(fn ($i) => $i->Non_unique == 0 && $i->Column_name === 'public_id');

    expect($unique)->not->toBeEmpty();
});

it('keeps the 3 auth providers untouched (registry is not a provider)', function () {
    // The registry must never be wired as an auth provider.
    expect(config('auth.providers.users'))->toBeNull();

    $providers = config('auth.providers');
    expect(array_keys($providers))->toContain('mahasiswa', 'dosen', 'admin');
});

it('backfills exactly one users row per auth row', function () {
    seedMasterData();

    $mahasiswa = \App\Models\Mahasiswa::factory()->create();
    $dosen = \App\Models\DosenPembimbing::factory()->create();
    $admin = \App\Models\Admin::factory()->create();

    // RefreshDatabase migrates once before the test transaction, so the
    // backfill (which runs inside that migration) saw an empty DB; run it now
    // against the rows we just created.
    (require database_path('migrations/2026_09_27_000600_backfill_users_registry.php'))->up();

    $expected = DB::table('mahasiswa')->count()
        + DB::table('dosen_pembimbing')->count()
        + DB::table('admin')->count();

    expect(DB::table('users')->count())->toBe($expected);

    // Each auth row is linked to a distinct registry row.
    expect(DB::table('users')->distinct()->count('public_id'))->toBe(DB::table('users')->count());
});

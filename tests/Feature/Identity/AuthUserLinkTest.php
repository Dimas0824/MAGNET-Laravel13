<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T3/T4: each auth table (mahasiswa/dosen_pembimbing/admin) gains a UNIQUE
 * `user_id` FK to the registry, and every existing row is linked (0 NULL).
 *
 * UNIQUE because one auth row maps to exactly one identity; the FK is how
 * cross-role references (chats, audit) resolve "who is this".
 */
const AUTH_TABLES = ['mahasiswa', 'dosen_pembimbing', 'admin'];

it('adds a unique user_id FK to each auth table', function () {
    foreach (AUTH_TABLES as $table) {
        expect(Schema::hasColumn($table, 'user_id'))->toBeTrue("{$table}.user_id missing");

        $unique = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->filter(fn ($i) => $i->Non_unique == 0 && $i->Column_name === 'user_id');

        expect($unique)->not->toBeEmpty("{$table}.user_id is not UNIQUE");

        $fk = DB::table('information_schema.referential_constraints')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->get()
            ->filter(fn ($r) => $r->REFERENCED_TABLE_NAME === 'users');

        expect($fk)->not->toBeEmpty("{$table}.user_id has no FK to users");
    }
});

it('backfills user_id on every auth row (0 NULL)', function () {
    seedMasterData();

    \App\Models\Mahasiswa::factory()->create();
    \App\Models\DosenPembimbing::factory()->create();
    \App\Models\Admin::factory()->create();

    (require database_path('migrations/2026_09_27_000600_backfill_users_registry.php'))->up();
    (require database_path('migrations/2026_09_27_000800_backfill_user_id_on_auth_tables.php'))->up();

    foreach (AUTH_TABLES as $table) {
        expect(DB::table($table)->whereNull('user_id')->count())->toBe(0, "{$table} has NULL user_id");
    }
});

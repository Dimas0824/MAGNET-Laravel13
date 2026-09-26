<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-T1/T2: collapse the 5 `kriteria_*` tables into ONE `mahasiswa_kriteria`
 * table with a typed nullable FK per criterion + a criteria_key discriminator.
 *
 * Five near-identical tables (each with its own mahasiswa_id FK) made "the 5
 * preferences of a student" a 5-way join and let a student hold two conflicting
 * rows. One table with a UNIQUE(mahasiswa_id, criteria_key) makes the shape
 * explicit and the invariant enforceable.
 */
it('creates mahasiswa_kriteria with the collapsed shape', function () {
    expect(Schema::hasTable('mahasiswa_kriteria'))->toBeTrue();

    $columns = fn () => DB::table('information_schema.columns')
        ->select('COLUMN_NAME')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'mahasiswa_kriteria')
        ->pluck('COLUMN_NAME')
        ->map(fn ($c) => strtolower((string) $c))
        ->all();

    $cols = $columns();

    foreach (['mahasiswa_id', 'criteria_key', 'pekerjaan_id', 'bidang_industri_id', 'lokasi_magang_id', 'value_enum', 'rank', 'bobot'] as $col) {
        expect($cols)->toContain($col);
    }
});

it('enforces UNIQUE(mahasiswa_id, criteria_key)', function () {
    $unique = collect(DB::select('SHOW INDEX FROM `mahasiswa_kriteria`'))
        ->filter(fn ($i) => $i->Non_unique == 0 && $i->Key_name !== 'PRIMARY')
        ->groupBy('Key_name')
        ->map(fn ($cols) => $cols->sortBy('Seq_in_index')->pluck('Column_name')->map(fn ($c) => strtolower($c))->values()->all());

    expect($unique->contains(fn ($cols) => $cols === ['mahasiswa_id', 'criteria_key']))->toBeTrue();
});

it('keeps bobot at decimal(30,15) until the post-parity resize', function () {
    $type = DB::table('information_schema.columns')
        ->select('COLUMN_TYPE')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'mahasiswa_kriteria')
        ->where('COLUMN_NAME', 'bobot')
        ->value('COLUMN_TYPE');

    expect($type)->toBe('decimal(30,15)');
});

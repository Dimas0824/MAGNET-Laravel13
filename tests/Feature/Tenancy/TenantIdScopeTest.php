<?php

use Illuminate\Support\Facades\DB;

/**
 * tenant_id lives on ROOT tables only (ADR 03). Children inherit tenancy via
 * their parent join. This test pins the exact root set so a stray or missing
 * tenant_id column fails loudly.
 */
const TENANT_ROOT_TABLES = [
    'users',
    'mahasiswa',
    'dosen_pembimbing',
    'admin',
    'bidang_industri',
    'pekerjaan',
    'lokasi_magang',
    'perusahaan',
    'lowongan_magang',
    'berkas_pengajuan_magang',
    'kontrak_magang',
    'recommendation_run',
];

beforeEach(function () {
    seedMasterData();
});

it('adds tenant_id to exactly the 12 root tables', function () {
    $tablesWithTenantId = DB::table('information_schema.columns')
        ->where('table_schema', DB::connection()->getDatabaseName())
        ->where('column_name', 'tenant_id')
        ->pluck('TABLE_NAME')
        ->map(fn ($t) => (string) $t)
        ->sort()
        ->values()
        ->all();

    $expected = collect(TENANT_ROOT_TABLES)->sort()->values()->all();

    expect($tablesWithTenantId)->toBe($expected);
});

it('makes tenant_id NOT NULL on every root table after the contract', function () {
    foreach (TENANT_ROOT_TABLES as $table) {
        $nullable = DB::table('information_schema.columns')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', 'tenant_id')
            ->value('is_nullable');

        expect($nullable)->toBe('NO', "{$table}.tenant_id should be NOT NULL after the P1-T7 contract");
    }
});

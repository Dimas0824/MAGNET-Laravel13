<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| kontrak_magang registration columns (W0-T0.2)
|--------------------------------------------------------------------------
|
| The kontrak lifecycle gains two nullable columns:
|   - tanggal_daftar    : the BUSINESS registration date (when the student
|                         registered), distinct from created_at which stays
|                         the canonical ROW-CREATION timestamp.
|   - surat_izin_path   : storage path of the permit letter (surat izin)
|                         uploaded for THIS contract.
|
| Placement rationale (why these live on kontrak_magang, not lowongan_magang):
|   kontrak_magang is 1:1 with the participant, while lowongan_magang partner
|   rows are SHARED across many applicants. The permit belongs to the
|   individual 1:1 kontrak, so it must NOT be pushed onto the shared lowongan.
|
| Two contracts under test:
|   1. both columns EXIST and are nullable (NULLABLE='YES' in information_schema), and
|   2. lowongan_magang does NOT carry surat_izin_path (regression guard against
|      reintroducing the permit on the shared row).
|
*/

it('kontrak_magang has nullable tanggal_daftar column', function () {
    expect(Schema::hasColumn('kontrak_magang', 'tanggal_daftar'))->toBeTrue();

    $nullable = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'kontrak_magang')
        ->where('COLUMN_NAME', 'tanggal_daftar')
        ->value('IS_NULLABLE');

    expect($nullable)->toBe('YES');
});

it('kontrak_magang has nullable surat_izin_path column', function () {
    expect(Schema::hasColumn('kontrak_magang', 'surat_izin_path'))->toBeTrue();

    $nullable = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'kontrak_magang')
        ->where('COLUMN_NAME', 'surat_izin_path')
        ->value('IS_NULLABLE');

    expect($nullable)->toBe('YES');
});

it('lowongan_magang does NOT carry surat_izin_path', function () {
    // Regression guard: the permit belongs on the 1:1 kontrak, not the
    // SHARED lowongan partner row.
    expect(Schema::hasColumn('lowongan_magang', 'surat_izin_path'))->toBeFalse();
});

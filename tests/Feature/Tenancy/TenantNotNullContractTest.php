<?php

use Illuminate\Support\Facades\DB;

/**
 * P1-T7 CONTRACT: after the backfill proved 0 NULL rows, `tenant_id` on every
 * root table must be NOT NULL, so an un-attributed row becomes impossible at
 * the schema level (the application scope can then be strict for everyone).
 *
 * The expand-phase NULL-inclusive behaviour is only safe because this contract
 * follows a verified backfill; this test pins the end state.
 */
it('makes tenant_id NOT NULL on all 12 root tables', function () {
    $roots = [
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

    foreach ($roots as $table) {
        $nullable = DB::selectOne(
            'SELECT IS_NULLABLE AS n FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, 'tenant_id']
        )->n ?? 'MISSING';

        expect($nullable)->toBe('NO', "{$table}.tenant_id should be NOT NULL");
    }
});

it('has a reversible down() that restores nullable tenant_id', function () {
    $migration = glob(database_path('migrations/*_make_tenant_id_not_null_on_roots.php'));

    expect($migration)->not->toBeEmpty();

    $source = file_get_contents($migration[0]);

    expect($source)->toContain('function down')
        ->and($source)->toContain('nullable');
});

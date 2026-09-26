<?php

use Illuminate\Support\Facades\DB;

/**
 * P6-T5 CONTRACT: business-critical FKs are ON DELETE RESTRICT, so deleting a
 * parent (student, company, contract) cannot silently destroy internship
 * evidence.
 */
it('makes the business-critical FKs RESTRICT', function () {
    $expect = [
        ['kontrak_magang', 'mahasiswa_id'],
        ['kontrak_magang', 'dosen_id'],
        ['kontrak_magang', 'lowongan_magang_id'],
        ['berkas_pengajuan_magang', 'mahasiswa_id'],
        ['lowongan_magang', 'perusahaan_id'],
        ['log_magang', 'kontrak_magang_id'],
        ['ulasan_magang', 'kontrak_magang_id'],
        ['umpan_balik_magang', 'kontrak_magang_id'],
    ];

    foreach ($expect as [$table, $column]) {
        $rule = DB::table('information_schema.key_column_usage as kcu')
            ->join('information_schema.referential_constraints as rc', function ($join) {
                $join->on('kcu.CONSTRAINT_NAME', '=', 'rc.CONSTRAINT_NAME')
                    ->on('kcu.CONSTRAINT_SCHEMA', '=', 'rc.CONSTRAINT_SCHEMA');
            })
            ->where('kcu.TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('kcu.TABLE_NAME', $table)
            ->where('kcu.COLUMN_NAME', $column)
            ->value('rc.DELETE_RULE');

        expect($rule)->toBe('RESTRICT', "{$table}.{$column} should be RESTRICT, got {$rule}");
    }
});

it('keeps stage rows under a run cascading (recomputable snapshot)', function () {
    $rule = DB::table('information_schema.key_column_usage as kcu')
        ->join('information_schema.referential_constraints as rc', function ($join) {
            $join->on('kcu.CONSTRAINT_NAME', '=', 'rc.CONSTRAINT_NAME')
                ->on('kcu.CONSTRAINT_SCHEMA', '=', 'rc.CONSTRAINT_SCHEMA');
        })
        ->where('kcu.TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('kcu.TABLE_NAME', 'ratio_system')
        ->where('kcu.COLUMN_NAME', 'run_id')
        ->value('rc.DELETE_RULE');

    expect($rule)->toBe('CASCADE');
});

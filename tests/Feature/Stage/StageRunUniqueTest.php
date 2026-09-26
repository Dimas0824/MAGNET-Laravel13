<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4a-T1: every stage table keys a recommendation run by (run_id,
 * lowongan_magang_id) — one row per alternative per run. Without the pair
 * unique, a re-run can silently duplicate stage rows and corrupt the ranking.
 *
 * The migration must DEDUP first, then add the unique index, so it is safe on
 * a database that already contains duplicates.
 */
const STAGE_TABLES = [
    'encoded_alternatives',
    'ratio_system',
    'reference_point',
    'full_multiplicative_form',
    'vector_normalization',
    'final_rank_recommendation',
];

it('adds a unique index on (run_id, lowongan_magang_id) to all 6 stage tables', function () {
    foreach (STAGE_TABLES as $table) {
        $indexes = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->filter(fn ($i) => $i->Non_unique == 0 && $i->Key_name !== 'PRIMARY')
            ->groupBy('Key_name')
            ->map(fn ($cols) => $cols->sortBy('Seq_in_index')->pluck('Column_name')->values()->all());

        $hasPair = $indexes->contains(
            fn ($cols) => $cols === ['run_id', 'lowongan_magang_id']
        );

        expect($hasPair)->toBeTrue("{$table} is missing UNIQUE(run_id, lowongan_magang_id)");
    }
});

it('rejects a duplicate (run_id, lowongan_magang_id) row in a stage table', function () {
    seedMasterData();

    $run = DB::table('recommendation_run')->insertGetId([
        'tenant_id' => \App\Models\Concerns\BelongsToTenant::defaultTenantId(),
        'mahasiswa_id' => \App\Models\Mahasiswa::factory()->create()->id,
        'run_key' => 'dup-test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $lowonganId = DB::table('lowongan_magang')->insertGetId([
        'tenant_id' => \App\Models\Concerns\BelongsToTenant::defaultTenantId(),
        'kuota' => 1,
        'pekerjaan_id' => DB::table('pekerjaan')->value('id'),
        'deskripsi' => 'd',
        'persyaratan' => 'p',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
        'status' => 'buka',
        'lokasi_magang_id' => DB::table('lokasi_magang')->value('id'),
        'perusahaan_id' => DB::table('perusahaan')->insertGetId([
            'tenant_id' => \App\Models\Concerns\BelongsToTenant::defaultTenantId(),
            'nama' => 'PT DUP',
            'bidang_industri_id' => DB::table('bidang_industri')->value('id'),
            'lokasi' => 'x',
            'kategori' => 'mitra',
            'website' => 'https://dup.test',
            'deskripsi' => 'd',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $row = [
        'run_id' => $run,
        'mahasiswa_id' => DB::table('mahasiswa')->value('id'),
        'lowongan_magang_id' => $lowonganId,
        'score' => 1.0,
        'rank' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('ratio_system')->insert($row);

    expect(fn () => DB::table('ratio_system')->insert($row))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

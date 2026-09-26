<?php

use App\Models\MahasiswaKriteria;
use App\Models\RecommendationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4b-T1: `mahasiswa_kriteria.bobot` resized decimal(30,15) -> decimal(6,3).
 *
 * V9 requires the criteria weight to be a (6,3) decimal, never (30,15). This is
 * the ONLY storage change that alters the physical string a bobot read returns,
 * so it is the one place a run_key could silently drift and orphan history. The
 * test proves both halves: the column really is (6,3), AND the derived run_key
 * is invariant under that change because makeKey canonicalizes to 3 decimals.
 *
 * (6,3) fits every ROC weight: ROC weights sum to 1, so each is in (0, 1], far
 * below the column's 999.999 ceiling.
 */
it('resizes mahasiswa_kriteria.bobot to decimal(6,3)', function () {
    $type = DB::table('information_schema.columns')
        ->select('COLUMN_TYPE')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'mahasiswa_kriteria')
        ->where('COLUMN_NAME', 'bobot')
        ->value('COLUMN_TYPE');

    expect(Schema::hasColumn('mahasiswa_kriteria', 'bobot'))->toBeTrue()
        ->and(strtolower((string) $type))->toBe('decimal(6,3)');
});

it('round-trips a ROC weight through the (6,3) column', function () {
    seedMasterData();

    $mahasiswa = \App\Models\Mahasiswa::factory()->create();
    $total = config('recommendation-system.roc.total_criteria');

    MahasiswaKriteria::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'criteria_key' => MahasiswaKriteria::KEY_PEKERJAAN,
        'rank' => 1,
        'bobot' => \App\Helpers\DecisionMaking\ROC::getWeight(1, $total),
    ]);

    $row = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_PEKERJAAN)
        ->first();

    // 0.4566666... stored as (6,3) reads back as the fixed 3-dp string.
    expect((string) $row->bobot)->toBe('0.457');
});

it('keeps the run_key stable across the bobot precision change (no orphan)', function () {
    $legacy = [
        'pekerjaan' => '0.456666666666670',
        'bidang_industri' => '0.256666666666670',
        'lokasi_magang' => '0.156666666666670',
        'jenis_magang' => '0.090000000000000',
        'open_remote' => '0.040000000000000',
    ];

    $resized = [
        'pekerjaan' => '0.457',
        'bidang_industri' => '0.257',
        'lokasi_magang' => '0.157',
        'jenis_magang' => '0.090',
        'open_remote' => '0.040',
    ];

    $alts = [
        ['lowongan_magang_id' => 11, 'pekerjaan' => 2, 'open_remote' => 2, 'jenis_magang' => 1, 'bidang_industri' => 2, 'lokasi_magang' => 2],
        ['lowongan_magang_id' => 22, 'pekerjaan' => 1, 'open_remote' => 2, 'jenis_magang' => 2, 'bidang_industri' => 1, 'lokasi_magang' => 2],
        ['lowongan_magang_id' => 33, 'pekerjaan' => 2, 'open_remote' => 1, 'jenis_magang' => 2, 'bidang_industri' => 2, 'lokasi_magang' => 1],
    ];

    $legacyKey = RecommendationRun::makeKey(4242, $alts, $legacy);
    $resizedKey = RecommendationRun::makeKey(4242, $alts, $resized);

    expect($legacyKey)->toBe($resizedKey);
});

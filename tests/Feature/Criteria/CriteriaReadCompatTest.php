<?php

use App\Helpers\DecisionMaking\ROC;
use App\Models\Mahasiswa;
use App\Models\MahasiswaKriteria;
use App\Models\Pekerjaan;
use Illuminate\Support\Facades\DB;

/**
 * P3-T4 read-compat bridge.
 *
 * The recommendation pipeline must read criteria from the NEW collapsed
 * `mahasiswa_kriteria` table while the OLD 5 `kriteria_*` tables still exist
 * (dropped later in P3-T6). These tests pin the source of truth: a row that
 * lives ONLY in `mahasiswa_kriteria` must be visible through every legacy read
 * path (`$mahasiswa->kriteriaPekerjaan`, `KriteriaPekerjaan::where(...)`, etc).
 *
 * If the relations still read the old table, the entries below (inserted only
 * into the collapsed table) are invisible and every assertion fails.
 */
beforeEach(function () {
    seedMasterData();
});

it('resolves $mahasiswa->kriteriaPekerjaan from mahasiswa_kriteria, not the old table', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $total = config('recommendation-system.roc.total_criteria');

    $pekerjaanId = Pekerjaan::where('nama', 'Software Engineer')->value('id');

    // Insert ONLY into the collapsed table — the old `kriteria_pekerjaan`
    // table stays empty for this mahasiswa.
    MahasiswaKriteria::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'criteria_key' => MahasiswaKriteria::KEY_PEKERJAAN,
        'pekerjaan_id' => $pekerjaanId,
        'rank' => 1,
        'bobot' => ROC::getWeight(1, $total),
    ]);

    expect(DB::table('kriteria_pekerjaan')->where('mahasiswa_id', $mahasiswa->id)->count())->toBe(0);

    $relasi = $mahasiswa->fresh()->kriteriaPekerjaan;

    expect($relasi)->not->toBeNull()
        ->and($relasi->mahasiswa_id)->toBe($mahasiswa->id)
        ->and($relasi->pekerjaan_id)->toBe($pekerjaanId)
        ->and((string) $relasi->bobot)->toBe('0.457');
});

it('resolves $mahasiswa->kriteriaJenisMagang->jenis_magang from value_enum', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $total = config('recommendation-system.roc.total_criteria');

    MahasiswaKriteria::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'criteria_key' => MahasiswaKriteria::KEY_JENIS_MAGANG,
        'value_enum' => 'berbayar',
        'rank' => 4,
        'bobot' => ROC::getWeight(4, $total),
    ]);

    $relasi = $mahasiswa->fresh()->kriteriaJenisMagang;

    expect($relasi)->not->toBeNull()
        // Legacy caller reads ->jenis_magang; the bridge maps it to value_enum.
        ->and($relasi->jenis_magang)->toBe('berbayar')
        ->and($relasi->value_enum)->toBe('berbayar');
});

it('resolves $mahasiswa->kriteriaOpenRemote->open_remote from value_enum', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $total = config('recommendation-system.roc.total_criteria');

    MahasiswaKriteria::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'criteria_key' => MahasiswaKriteria::KEY_OPEN_REMOTE,
        'value_enum' => 'ya',
        'rank' => 5,
        'bobot' => ROC::getWeight(5, $total),
    ]);

    $relasi = $mahasiswa->fresh()->kriteriaOpenRemote;

    expect($relasi->open_remote)->toBe('ya')
        ->and($relasi->value_enum)->toBe('ya');
});

it('makes the legacy KriteriaPekerjaan query read the collapsed table', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $total = config('recommendation-system.roc.total_criteria');

    MahasiswaKriteria::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'criteria_key' => MahasiswaKriteria::KEY_PEKERJAAN,
        'pekerjaan_id' => Pekerjaan::where('nama', 'Data Engineer')->value('id'),
        'rank' => 1,
        'bobot' => ROC::getWeight(1, $total),
    ]);

    // The legacy static query must find the collapsed row and only that
    // criteria_key's row (no cross-criterion bleed).
    $row = \App\Models\KriteriaPekerjaan::where('mahasiswa_id', $mahasiswa->id)->first();

    expect($row)->not->toBeNull()
        ->and((string) $row->bobot)->toBe('0.457')
        ->and(\App\Models\KriteriaPekerjaan::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        // jenis_magang lives under a different key and must not leak in.
        ->and(\App\Models\KriteriaJenisMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(0);
});

it('writes through the legacy model into mahasiswa_kriteria (single source of truth)', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $total = config('recommendation-system.roc.total_criteria');

    \App\Models\KriteriaJenisMagang::firstOrNew(['mahasiswa_id' => $mahasiswa->id])
        ->forceFill([
            'jenis_magang' => 'tidak berbayar',
            'rank' => 4,
            'bobot' => ROC::getWeight(4, $total),
        ])->save();

    $collapsed = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_JENIS_MAGANG)
        ->first();

    expect($collapsed)->not->toBeNull()
        ->and($collapsed->value_enum)->toBe('tidak berbayar')
        ->and(DB::table('kriteria_jenis_magang')->where('mahasiswa_id', $mahasiswa->id)->count())->toBe(0);
});

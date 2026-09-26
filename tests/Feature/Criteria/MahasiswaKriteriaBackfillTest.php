<?php

use App\Helpers\DecisionMaking\ROC;
use App\Models\BidangIndustri;
use App\Models\LokasiMagang;
use App\Models\Mahasiswa;
use App\Models\MahasiswaKriteria;
use App\Models\Pekerjaan;
use Illuminate\Support\Facades\DB;

/**
 * P3-T2: the backfill copies every criterion row into mahasiswa_kriteria with
 * the bobot VALUE preserved (resized to the canonical (6,3) form in P4b; the
 * run_key parity gate is precision-independent, so the value is what matters).
 *
 * P3-T4 note: `mahasiswaDenganPreferensi()` now writes straight to the
 * collapsed table, so this test seeds the LEGACY `kriteria_*` tables directly
 * (that is exactly what the backfill migration reads from).
 */
function seedLegacyKriteria(Mahasiswa $mahasiswa): void
{
    $total = config('recommendation-system.roc.total_criteria');

    DB::table('kriteria_pekerjaan')->insert([
        'mahasiswa_id' => $mahasiswa->id,
        'pekerjaan_id' => Pekerjaan::where('nama', 'Software Engineer')->value('id'),
        'rank' => 1,
        'bobot' => ROC::getWeight(1, $total),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('kriteria_bidang_industri')->insert([
        'mahasiswa_id' => $mahasiswa->id,
        'bidang_industri_id' => BidangIndustri::where('nama', 'Teknologi')->value('id'),
        'rank' => 2,
        'bobot' => ROC::getWeight(2, $total),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('kriteria_lokasi_magang')->insert([
        'mahasiswa_id' => $mahasiswa->id,
        'lokasi_magang_id' => LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
        'rank' => 3,
        'bobot' => ROC::getWeight(3, $total),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('kriteria_jenis_magang')->insert([
        'mahasiswa_id' => $mahasiswa->id,
        'jenis_magang' => 'berbayar',
        'rank' => 4,
        'bobot' => ROC::getWeight(4, $total),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('kriteria_open_remote')->insert([
        'mahasiswa_id' => $mahasiswa->id,
        'open_remote' => 'ya',
        'rank' => 5,
        'bobot' => ROC::getWeight(5, $total),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function freshMahasiswaTanpaPreferensi(): Mahasiswa
{
    seedMasterData();

    return Mahasiswa::factory()->create();
}

it('backfills one mahasiswa_kriteria row per source criterion row', function () {
    $mahasiswa = freshMahasiswaTanpaPreferensi();
    seedLegacyKriteria($mahasiswa);

    // Re-run the backfill against the rows the test just created (the migration
    // ran before the test transaction).
    (require database_path('migrations/2026_09_27_001800_backfill_mahasiswa_kriteria.php'))->up();

    $rows = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)->get();

    expect($rows)->toHaveCount(5)
        ->and($rows->pluck('criteria_key')->sort()->values()->all())
        ->toBe(['bidang_industri', 'jenis_magang', 'lokasi_magang', 'open_remote', 'pekerjaan']);
});

it('copies the bobot VALUE into the (6,3) column (3-dp canonical string)', function () {
    $mahasiswa = freshMahasiswaTanpaPreferensi();
    seedLegacyKriteria($mahasiswa);

    (require database_path('migrations/2026_09_27_001800_backfill_mahasiswa_kriteria.php'))->up();

    $pekerjaan = DB::table('kriteria_pekerjaan')->where('mahasiswa_id', $mahasiswa->id)->value('bobot');
    $collapsed = DB::table('mahasiswa_kriteria')
        ->where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', 'pekerjaan')
        ->value('bobot');

    // Legacy source is (30,15) => '0.456666666666670'; the collapsed column is
    // (6,3) after P4b, so the stored value is the canonical 3-dp form.
    // The (6,3) column rounds the source value to 3 decimals; the stored value is
    // the canonical form of the source, not its byte-identical copy.
    expect((string) $collapsed)->toBe(number_format((float) $pekerjaan, 3, '.', ''))
        ->and((string) $collapsed)->toBe('0.457');
});

it('maps each criterion to its typed FK or enum column', function () {
    $mahasiswa = freshMahasiswaTanpaPreferensi();
    seedLegacyKriteria($mahasiswa);

    (require database_path('migrations/2026_09_27_001800_backfill_mahasiswa_kriteria.php'))->up();

    $byKey = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)->get()->keyBy('criteria_key');

    expect($byKey['pekerjaan']->pekerjaan_id)->not->toBeNull()
        ->and($byKey['bidang_industri']->bidang_industri_id)->not->toBeNull()
        ->and($byKey['lokasi_magang']->lokasi_magang_id)->not->toBeNull()
        ->and($byKey['jenis_magang']->value_enum)->toBe('berbayar')
        ->and($byKey['open_remote']->value_enum)->toBe('ya');
});


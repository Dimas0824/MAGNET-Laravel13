<?php

use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\Storage;

/**
 * P5-T3: BOTH location consumers move off `perusahaan.lokasi` free text onto
 * the `lokasi_magang` lookup (reached via `lowongan_magang.lokasi_magang_id`):
 *   1. the mahasiswa dashboard's `categorizeLocation()` string matching, and
 *   2. DataPreprocessing::dataCategorization().
 *
 * After the change, `perusahaan.lokasi` is never read, so it can be dropped
 * (P5-T4). This test pins that the categorized value equals the lookup's
 * `kategori_lokasi` — not a value derived from the free-text column.
 */
beforeEach(function () {
    Storage::fake('local');
    seedMasterData();
});

it('dashboard renders the lokasi as the lookup, not free-text matching', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    $perusahaan = Perusahaan::factory()->create();
    $lokasiId = \App\Models\LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id');

    $lowongan = lowonganMagang([
        'perusahaan_id' => $perusahaan->id,
        'lokasi_magang_id' => $lokasiId,
    ]);

    \App\Models\RatioSystem::forceCreate([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id, 'score' => 1, 'rank' => 1,
    ]);
    $rpId = \Illuminate\Support\Facades\DB::table('reference_point')->insertGetId([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
        'pekerjaan' => 0, 'open_remote' => 0, 'jenis_magang' => 0, 'bidang_industri' => 0, 'lokasi_magang' => 0,
        'max_score' => 0.1, 'rank' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $fmf = \App\Models\FullMultiplicativeForm::forceCreate([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id, 'score' => 1, 'rank' => 1,
    ]);
    \App\Models\FinalRankRecommendation::forceCreate([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
        'ratio_system_id' => \App\Models\RatioSystem::where('lowongan_magang_id', $lowongan->id)->value('id'),
        'reference_point_id' => $rpId, 'fmf_id' => $fmf->id, 'avg_rank' => 1, 'rank' => 1,
    ]);

    actingAsMahasiswa($mahasiswa);

    // The lookup category must appear; the old free-text fallback must NOT.
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Area Malang Raya')
        ->assertDontSee('Luar Provinsi Jawa Timur');});

it('dataCategorization derives lokasi from the lookup, not free text', function () {
    $perusahaan = Perusahaan::factory()->create();
    $lokasiId = \App\Models\LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id');

    $lowongan = lowonganMagang([
        'perusahaan_id' => $perusahaan->id,
        'lokasi_magang_id' => $lokasiId,
    ]);

    DataPreprocessing::dataCategorization($lowongan);

    $path = config('recommendation-system.preprocessing.alternatives_categorized_path');
    $row = collect(Storage::json($path))->firstWhere('id', $lowongan->id);

    expect($row['lokasi_magang'])->toBe('Area Malang Raya');
});

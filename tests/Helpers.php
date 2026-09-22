<?php

use App\Helpers\DecisionMaking\ROC;
use App\Models\BidangIndustri;
use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;

/**
 * Seed the master data (bidang industri, pekerjaan, lokasi) that factories
 * and the recommendation pipeline depend on. Safe to call multiple times.
 */
function seedMasterData(): void
{
    if (BidangIndustri::query()->exists()) {
        return;
    }

    foreach (['Semua', 'Teknologi', 'Perbankan', 'Kesehatan'] as $nama) {
        BidangIndustri::create(['nama' => $nama]);
    }

    foreach (['Semua', 'Software Engineer', 'Data Engineer', 'UI/UX Designer'] as $nama) {
        Pekerjaan::create(['nama' => $nama]);
    }

    $locations = [
        'Semua' => ['Semua lokasi'],
        'Area Malang Raya' => ['Lowokwaru, Kota Malang, Jawa Timur'],
        'Luar area Malang Raya (dalam Jawa Timur)' => ['Semarang, Jawa Tengah'],
        'Luar provinsi Jawa Timur' => ['Jakarta Selatan, DKI Jakarta'],
        'Luar negeri' => ['Tokyo, Jepang'],
    ];

    foreach ($locations as $kategori => $locs) {
        foreach ($locs as $loc) {
            LokasiMagang::create([
                'kategori_lokasi' => $kategori,
                'lokasi' => $loc,
            ]);
        }
    }
}

/**
 * Build a lengkap mahasiswa with the 5 criterion preferences set.
 * Returns the persisted model.
 */
function mahasiswaDenganPreferensi(array $overrides = []): Mahasiswa
{
    seedMasterData();

    $mahasiswa = Mahasiswa::factory()->create($overrides);

    $total = config('recommendation-system.roc.total_criteria');

    KriteriaPekerjaan::create([
        'mahasiswa_id' => $mahasiswa->id,
        'pekerjaan_id' => Pekerjaan::where('nama', 'Software Engineer')->value('id'),
        'rank' => 1,
        'bobot' => ROC::getWeight(1, $total),
    ]);

    KriteriaBidangIndustri::create([
        'mahasiswa_id' => $mahasiswa->id,
        'bidang_industri_id' => BidangIndustri::where('nama', 'Teknologi')->value('id'),
        'rank' => 2,
        'bobot' => ROC::getWeight(2, $total),
    ]);

    KriteriaLokasiMagang::create([
        'mahasiswa_id' => $mahasiswa->id,
        'lokasi_magang_id' => LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
        'rank' => 3,
        'bobot' => ROC::getWeight(3, $total),
    ]);

    KriteriaJenisMagang::create([
        'mahasiswa_id' => $mahasiswa->id,
        'jenis_magang' => 'berbayar',
        'rank' => 4,
        'bobot' => ROC::getWeight(4, $total),
    ]);

    KriteriaOpenRemote::create([
        'mahasiswa_id' => $mahasiswa->id,
        'open_remote' => 'ya',
        'rank' => 5,
        'bobot' => ROC::getWeight(5, $total),
    ]);

    return $mahasiswa->refresh();
}

/**
 * Build a lowongan magang with its company + relations, bypassing model
 * events so the recommendation pipeline is not triggered unintentionally.
 * Returns the persisted model.
 */
function lowonganMagang(array $overrides = []): LowonganMagang
{
    seedMasterData();

    $perusahaan = isset($overrides['perusahaan_id'])
        ? Perusahaan::find($overrides['perusahaan_id'])
        : Perusahaan::factory()->create();

    $lowongan = LowonganMagang::withoutEvents(fn () => LowonganMagang::create(array_merge([
        'kuota' => 5,
        'pekerjaan_id' => Pekerjaan::where('nama', 'Software Engineer')->value('id'),
        'deskripsi' => 'Deskripsi magang',
        'persyaratan' => 'Persyaratan magang',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
        'status' => 'buka',
        'lokasi_magang_id' => LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
        'perusahaan_id' => $perusahaan->id,
    ], $overrides)));

    return $lowongan;
}

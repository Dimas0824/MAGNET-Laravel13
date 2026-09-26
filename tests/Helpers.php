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
 *
 * Also ensures the default tenant exists FIRST, so the BelongsToTenant creating
 * hook stamps every row with a real tenant_id (otherwise rows are written with
 * a NULL tenant_id and the tenant scope hides them from later reads).
 */
function seedMasterData(): void
{
    if (! \App\Models\Tenant::query()->exists()) {
        (new \Database\Seeders\TenantSeeder)->run();
    }

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
 */function mahasiswaDenganPreferensi(array $overrides = []): Mahasiswa
{
    seedMasterData();

    $mahasiswa = Mahasiswa::factory()->create($overrides);

    $total = config('recommendation-system.roc.total_criteria');

    KriteriaPekerjaan::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'pekerjaan_id' => Pekerjaan::where('nama', 'Software Engineer')->value('id'),
        'rank' => 1,
        'bobot' => ROC::getWeight(1, $total),
    ]);

    KriteriaBidangIndustri::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'bidang_industri_id' => BidangIndustri::where('nama', 'Teknologi')->value('id'),
        'rank' => 2,
        'bobot' => ROC::getWeight(2, $total),
    ]);

    KriteriaLokasiMagang::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'lokasi_magang_id' => LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
        'rank' => 3,
        'bobot' => ROC::getWeight(3, $total),
    ]);

    KriteriaJenisMagang::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'jenis_magang' => 'berbayar',
        'rank' => 4,
        'bobot' => ROC::getWeight(4, $total),
    ]);

    KriteriaOpenRemote::forceCreate([
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

    return LowonganMagang::withoutEvents(fn () => LowonganMagang::forceCreate(array_merge([
        'tenant_id' => \App\Models\Concerns\BelongsToTenant::defaultTenantId(),
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
}

/**
 * Link the kontrak's mahasiswa + dosen into the users registry and stamp their
 * `user_id`, so chat fixtures can reference the registry FKs.
 *
 * RefreshDatabase migrates once before the test transaction, so the registry
 * backfill (which runs inside that migration) saw an empty DB; re-run it here
 * against the rows the test just created.
 */
function linkKontrakParticipantsToRegistry(\App\Models\KontrakMagang $kontrak): void
{
    (new \Database\Seeders\TenantBackfillSeeder)->run();
    (require database_path('migrations/2026_09_27_000600_backfill_users_registry.php'))->up();
    (require database_path('migrations/2026_09_27_000800_backfill_user_id_on_auth_tables.php'))->up();

    $kontrak->mahasiswa?->refresh();
    $kontrak->dosenPembimbing?->refresh();
}

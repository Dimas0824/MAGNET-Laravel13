<?php

use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use App\Models\UlasanMagang;
use Illuminate\Support\Facades\DB;

/**
 * Query budget for a single render of the company profile page.
 *
 * Pre-refactor the page ran 10 queries, one of which (Q10) was a redundant
 * render-time `count(*)` on lowongan_magang that duplicated rows already
 * fetched earlier in the same request. The refactor resolves the active-opening
 * count from in-memory state, so the ceiling is tightened to 9: if the count
 * query ever comes back, this test fails.
 */
const BASELINE_PROFIL_PERUSAHAAN_QUERIES = 9;

beforeEach(function () {
    seedMasterData();
});

it('renders the company profile page', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $perusahaan = Perusahaan::factory()->create();
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);

    $this->get(route('mahasiswa.profil-perusahaan', $perusahaan->id))->assertOk();
});

it('lists other open openings from the same company', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $perusahaan = Perusahaan::factory()->create();
    $opening = lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);

    $this->get(route('mahasiswa.profil-perusahaan', $perusahaan->id))
        ->assertOk()
        ->assertSee($opening->pekerjaan->nama);
});

it('does not write to the database during a plain page render', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $perusahaan = Perusahaan::factory()->create(['rating' => 4.5]);
    $opening = lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    $kontrak = KontrakMagang::factory()->create(['lowongan_magang_id' => $opening->id]);
    UlasanMagang::create(['kontrak_magang_id' => $kontrak->id, 'rating' => 1, 'komentar' => 'bad']);

    $before = $perusahaan->fresh()->rating;

    $this->get(route('mahasiswa.profil-perusahaan', $perusahaan->id))->assertOk();

    // A GET must not mutate the company rating (was a DB write in a getter).
    expect($perusahaan->fresh()->rating)->toBe($before);
});

it('renders the active-opening count and stays within the query budget', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $perusahaan = Perusahaan::factory()->create();

    // 4 open + 1 closed; only the 4 open ones count toward the total.
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'tutup']);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $response = $this->get(route('mahasiswa.profil-perusahaan', $perusahaan->id));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();

    // The total counts every open opening for the company (not the closed one),
    // and the "Lihat Semua Lowongan" CTA renders once there are more than 3.
    $response->assertSee('Lihat Semua Lowongan (4)', false);

    // Query budget: the active-opening count must be resolved without an extra
    // render-time query. The refactor must not make the query count worse than
    // the pre-refactor baseline for this page.
    expect($queryCount)->toBeLessThanOrEqual(BASELINE_PROFIL_PERUSAHAAN_QUERIES);
});

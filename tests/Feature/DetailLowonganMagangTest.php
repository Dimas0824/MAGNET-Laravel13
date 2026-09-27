<?php

use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Models\UlasanMagang;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

it('renders the lowongan detail page', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $lowongan = lowonganMagang(['status' => 'buka']);

    $this->get(route('mahasiswa.detail-lowongan-magang', $lowongan->id))->assertOk();
});

it('shows similar openings and links them to a defined route', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $pekerjaanId = Pekerjaan::where('nama', 'Software Engineer')->value('id');
    $current = lowonganMagang(['status' => 'buka', 'pekerjaan_id' => $pekerjaanId]);
    $sibling = lowonganMagang(['status' => 'buka', 'pekerjaan_id' => $pekerjaanId]);

    $response = $this->get(route('mahasiswa.detail-lowongan-magang', $current->id));
    $response->assertOk();

    // The sibling opening should be discoverable and its company profile
    // link must resolve to the defined route.
    $response->assertSee(route('mahasiswa.profil-perusahaan', $sibling->perusahaan_id), false);
});

it('shows reviews from contracts for the opening', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $lowongan = lowonganMagang(['status' => 'buka']);
    $kontrak = KontrakMagang::factory()->create(['lowongan_magang_id' => $lowongan->id]);

    UlasanMagang::create([
        'kontrak_magang_id' => $kontrak->id,
        'rating' => 5,
        'komentar' => 'Pengalaman magang menyenangkan',
    ]);

    $this->get(route('mahasiswa.detail-lowongan-magang', $lowongan->id))
        ->assertOk()
        ->assertSee('Pengalaman magang menyenangkan');
});

it('loads the detail without N+1', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $pekerjaanId = Pekerjaan::where('nama', 'Software Engineer')->value('id');
    $current = lowonganMagang(['status' => 'buka', 'pekerjaan_id' => $pekerjaanId]);

    // Create several similar openings sharing the same pekerjaan (status buka)
    // so a naive implementation would issue one query per rendered sibling.
    foreach (range(1, 4) as $i) {
        lowonganMagang(['status' => 'buka', 'pekerjaan_id' => $pekerjaanId]);
    }

    // Warm caches (auth guard, route, etc.) so we measure only the page's
    // data-loading queries, not framework bootstrapping.
    $this->get(route('mahasiswa.detail-lowongan-magang', $current->id))->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get(route('mahasiswa.detail-lowongan-magang', $current->id))->assertOk();

    // Budget: the detail page (lowongan + perusahaan + pekerjaan + lokasi +
    // 4 similar openings with their perusahaan/pekerjaan, plus reviews) must
    // stay constant regardless of how many similar openings exist. Any N+1
    // (per-sibling query) would push this well above the budget.
    expect($queries)->toBeLessThanOrEqual(20);
});

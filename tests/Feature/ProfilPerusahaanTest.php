<?php

use App\Models\KontrakMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use App\Models\UlasanMagang;

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

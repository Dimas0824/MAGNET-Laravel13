<?php

use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Models\UlasanMagang;

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

<?php

use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use App\Models\BidangIndustri;

beforeEach(function () {
    seedMasterData();
});

it('renders the search page for an authenticated mahasiswa', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    lowonganMagang();

    $this->get(route('mahasiswa.hasil-pencarian'))->assertOk();
});

it('filters openings by a search query without erroring', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    lowonganMagang();

    // The opening has no "nama" column; the query must still succeed and must
    // not reference the dropped nama column.
    $this->get(route('mahasiswa.hasil-pencarian', ['query' => 'Software']))
        ->assertOk();
});

it('filters openings by company name', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $perusahaan = Perusahaan::factory()->create(['nama' => 'PT Magnet Sejahtera']);
    lowonganMagang(['perusahaan_id' => $perusahaan->id]);

    $this->get(route('mahasiswa.hasil-pencarian', ['query' => 'Magnet']))
        ->assertOk();
});

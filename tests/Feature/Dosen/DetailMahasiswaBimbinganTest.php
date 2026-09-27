<?php

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Livewire\Volt\Volt;

/**
 * Characterization for the dosen "detail mahasiswa bimbingan" page (W2-T05).
 *
 * Locks the CURRENT behavior before replacing the raw 5-table join in the
 * `$mahasiswaDetail` computed with Eloquent relations: the rendered card
 * fields (nama/nim/prodi/perusahaan/posisi) and the contract window.
 */
beforeEach(function () {
    seedMasterData();
});

it('renders the bimbingan detail card from the contract relations', function () {
    $dosen = DosenPembimbing::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Bimbingan Satu']);
    $perusahaan = \App\Models\Perusahaan::factory()->create(['nama' => 'PT Contoh']);
    $pekerjaan = \App\Models\Pekerjaan::where('nama', 'Software Engineer')->first();

    $lowongan = lowonganMagang([
        'perusahaan_id' => $perusahaan->id,
        'pekerjaan_id' => $pekerjaan->id,
    ]);

    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => $lowongan->id,
        'waktu_awal' => now()->subDays(10),
        'waktu_akhir' => now()->addDays(20),
    ]);

    actingAsDosen($dosen);

    Volt::test('pages.dosen.detail-mahasiswa-bimbingan', ['id' => $mahasiswa->id])
        ->assertOk()
        ->assertSee('Bimbingan Satu')
        ->assertSee('PT Contoh')
        ->assertSee('Software Engineer');
});

it('404s when the dosen has no contract for that mahasiswa', function () {
    $dosen = DosenPembimbing::factory()->create();
    $other = Mahasiswa::factory()->create();
    actingAsDosen($dosen);

    Volt::test('pages.dosen.detail-mahasiswa-bimbingan', ['id' => $other->id])
        ->assertStatus(404);
});

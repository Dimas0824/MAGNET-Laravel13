<?php

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Mahasiswa bimbingan list (W2 / BUG5)
|--------------------------------------------------------------------------
|
| The dosen "Mahasiswa Bimbingan Aktif" page mirrors the (previously broken)
| inline filter `->where('mahasiswa.status_magang', '!=', 'selesai')`. Because
| the enum value is 'selesai magang' (WITH a space), that filter matched every
| row and a FINISHED student still appeared in the list. The view + scope now
| filter the intended value, so a finished student's kontrak must not render.
|
*/

beforeEach(function () {
    seedMasterData();
});

it('lists only non-finished students and hides finished ones', function () {
    $dosen = DosenPembimbing::factory()->create();

    $aktif = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Aktif']);
    $aktif->forceFill(['status_magang' => 'sedang magang'])->save();

    $selesai = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Selesai']);
    $selesai->forceFill(['status_magang' => 'selesai magang'])->save();

    KontrakMagang::factory()->create([
        'mahasiswa_id' => $aktif->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => lowonganMagang()->id,
    ]);
    KontrakMagang::factory()->create([
        'mahasiswa_id' => $selesai->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => lowonganMagang()->id,
    ]);

    actingAsDosen($dosen);

    Volt::test('pages.dosen.mahasiswa-bimbingan')
        ->assertOk()
        ->assertSee($aktif->nama)
        ->assertDontSee($selesai->nama);
});

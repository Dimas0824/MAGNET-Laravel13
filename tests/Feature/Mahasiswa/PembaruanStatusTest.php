<?php

use App\Models\BerkasPengajuanMagang;
use App\Models\FormPengajuanMagang;
use App\Models\Mahasiswa;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| PembaruanStatus — the mahasiswa "pembaruan-status" Volt page (BUG2)
|--------------------------------------------------------------------------
|
| resources/views/pages/mahasiswa/pembaruan-status.blade.php
|
| `mahasiswa.status_magang` is a SPACE-form enum
| ('belum magang' | 'sedang magang' | 'selesai magang'), and it is NOT
| fillable (deliberate mass-assignment hardening, see MassAssignmentTest).
|
| The page was broken two ways:
|   1. the write used `$this->mahasiswa->update([...])`, silently dropped
|      because status_magang is not fillable;
|   2. convertDisplayStatusToDb() returned UNDERSCORE values that match no
|      enum value.
|
| The fix is a coordinated four-edit change; this suite locks the behavior:
|   - a valid "Belum Magang" -> "Sedang Magang" transition persists the
|     SPACE value via forceFill and flashes success;
|   - the B1 business guard still fires (the literal was retargeted to
|     'sedang magang') so a mahasiswa with no approved application is
|     blocked;
|   - an illegal transition (Belum Magang -> Selesai Magang) is rejected.
|
*/

beforeEach(function () {
    seedMasterData();
});

/**
 * A mahasiswa pinned to "belum magang" (the factory force-fills a RANDOM
 * status in afterMaking()).
 */
function pembaruanStatusMahasiswa(): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->forceFill(['status_magang' => 'belum magang'])->save();

    return $mahasiswa;
}

/**
 * Attach an approved (status 'diterima') FormPengajuanMagang to the mahasiswa
 * so the "Sedang Magang" business guard passes.
 *
 * NOTE: FormPengajuanMagangFactory randomizes `status` in afterMaking(), so
 * force the 'diterima' value after creation.
 */
function approveApplicationFor(Mahasiswa $mahasiswa): FormPengajuanMagang
{
    $berkas = BerkasPengajuanMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
    ]);

    $form = FormPengajuanMagang::factory()->create([
        'pengajuan_id' => $berkas->id,
    ]);
    $form->forceFill(['status' => 'diterima'])->save();

    return $form;
}

it('updates status_magang to the space-form enum value', function () {
    $mahasiswa = pembaruanStatusMahasiswa();

    // Approved application present so the "Sedang Magang" guard passes; no
    // pending contract is created, so hasPendingContract stays false.
    approveApplicationFor($mahasiswa);

    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.pembaruan-status')
        ->set('status', 'Sedang Magang')
        ->call('updateStatus')
        ->assertSee('Status magang berhasil diperbarui ke: Sedang Magang');

    expect(Mahasiswa::find($mahasiswa->id)->status_magang)->toBe('sedang magang');
});

it('still blocks sedang magang when there is no approved application', function () {
    $mahasiswa = pembaruanStatusMahasiswa();

    // No approved FormPengajuanMagang -> the B1 guard must reject.
    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.pembaruan-status')
        ->set('status', 'Sedang Magang')
        ->call('updateStatus')
        ->assertSee('Anda harus mendapat persetujuan admin terlebih dahulu sebelum dapat mengubah status ke "Sedang Magang".');

    expect(Mahasiswa::find($mahasiswa->id)->status_magang)->toBe('belum magang');
});

it('blocks Belum Magang -> Selesai Magang', function () {
    $mahasiswa = pembaruanStatusMahasiswa();

    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.pembaruan-status')
        ->set('status', 'Selesai Magang')
        ->call('updateStatus')
        ->assertSee('Perubahan status tidak diizinkan. Silakan ikuti alur status yang benar.');

    expect(Mahasiswa::find($mahasiswa->id)->status_magang)->toBe('belum magang');
});

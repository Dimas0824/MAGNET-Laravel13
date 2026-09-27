<?php

use App\Models\KontrakMagang;
use Illuminate\Database\QueryException;

/*
|--------------------------------------------------------------------------
| kontrak_magang.status enum -> 'selesai' (W0-T0.1)
|--------------------------------------------------------------------------
|
| The kontrak lifecycle gains a terminal 'selesai' (finished) state. The
| original migration (2025_06_16_192800_add_status_to_kontrak_magang_table)
| only allowed ['menunggu_persetujuan', 'disetujui', 'ditolak']; this pins the
| widened domain.
|
| Two contracts under test:
|   1. the enum ACCEPTS 'selesai' (insert + refresh does not throw), and
|   2. the enum STILL REJECTS unknown values (MySQL strict mode -> QueryException),
|      so widening did not accidentally turn the column into a free-form string.
|
*/

beforeEach(function () {
    seedMasterData();
});

it('kontrak_magang.status enum accepts selesai', function () {
    $lowongan = lowonganMagang();

    // forceCreate bypasses mass-assignment only; the DB enum still validates.
    // Pre-fix (enum without 'selesai'), MySQL strict mode rejects the value:
    // "Data truncated for column 'status' at row 1".
    $kontrak = KontrakMagang::forceCreate([
        'mahasiswa_id' => \App\Models\Mahasiswa::factory()->create()->id,
        'dosen_id' => \App\Models\DosenPembimbing::factory()->create()->id,
        'lowongan_magang_id' => $lowongan->id,
        'waktu_awal' => now()->subDays(30),
        'waktu_akhir' => now(),
        'status' => 'selesai',
    ]);

    $kontrak->refresh();

    expect($kontrak->status)->toBe('selesai');

    test()->assertDatabaseHas('kontrak_magang', [
        'id' => $kontrak->id,
        'status' => 'selesai',
    ]);
});

it('kontrak_magang.status enum still rejects unknown values', function () {
    $lowongan = lowonganMagang();

    expect(fn () => KontrakMagang::forceCreate([
        'mahasiswa_id' => \App\Models\Mahasiswa::factory()->create()->id,
        'dosen_id' => \App\Models\DosenPembimbing::factory()->create()->id,
        'lowongan_magang_id' => $lowongan->id,
        'waktu_awal' => now()->subDays(30),
        'waktu_akhir' => now(),
        'status' => 'bogus',
    ]))->toThrow(QueryException::class);
});

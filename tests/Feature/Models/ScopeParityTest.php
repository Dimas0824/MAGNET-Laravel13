<?php

use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;

/*
|--------------------------------------------------------------------------
| Scope parity
|--------------------------------------------------------------------------
|
| Each scope we extract from an inline view query MUST return exactly the
| same rows as the inline chain it mirrors. These tests copy the OLD inline
| chain verbatim as the oracle and assert identical plucked ids, so adding
| the scope can never change behaviour.
|
*/

beforeEach(function () {
    seedMasterData();
});

it('scope buka returns the same ids as the inline where status buka chain', function () {
    // Mirrors resources/views/pages/mahasiswa/profil-perusahaan.blade.php:61
    // and resources/views/pages/mahasiswa/detail-lowongan-magang.blade.php:64
    // (->where('status', 'buka')).
    $buka1 = lowonganMagang(['status' => 'buka']);
    $buka2 = lowonganMagang(['status' => 'buka']);
    lowonganMagang(['status' => 'tutup']);
    lowonganMagang(['status' => 'tutup']);

    $oracle = LowonganMagang::query()
        ->where('status', 'buka')
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $viaScope = LowonganMagang::query()
        ->buka()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    // Guard: the fixture genuinely discriminates (2 open vs 2 closed).
    expect($oracle)->toHaveCount(2)
        ->and($oracle)->toEqualCanonicalizing([$buka1->id, $buka2->id]);

    expect($viaScope)->toBe($oracle);
});

it('scope forPerusahaan returns the same ids as the inline where perusahaan_id chain', function () {
    // Mirrors resources/views/components/mahasiswa/pembaruan-status-magang/sedang-magang.blade.php:103
    // (LowonganMagang::where('perusahaan_id', $this->selected_company_id)...).
    $perusahaan = Perusahaan::factory()->create();

    $mine = lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    $mine2 = lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'tutup']);
    lowonganMagang(); // different company

    $oracle = LowonganMagang::query()
        ->where('perusahaan_id', $perusahaan->id)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $viaScope = LowonganMagang::query()
        ->forPerusahaan($perusahaan->id)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($oracle)->toHaveCount(2)
        ->and($oracle)->toEqualCanonicalizing([$mine->id, $mine2->id]);

    expect($viaScope)->toBe($oracle);
});

it('composes buka and forPerusahaan like the inline chain', function () {
    // Mirrors resources/views/components/mahasiswa/pembaruan-status-magang/sedang-magang.blade.php:103-104
    // (->where('perusahaan_id', ...)->where('status', 'buka')).
    $perusahaan = Perusahaan::factory()->create();

    $match = lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'buka']);
    lowonganMagang(['perusahaan_id' => $perusahaan->id, 'status' => 'tutup']);
    lowonganMagang(['status' => 'buka']); // different company

    $oracle = LowonganMagang::query()
        ->where('perusahaan_id', $perusahaan->id)
        ->where('status', 'buka')
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $viaScope = LowonganMagang::query()
        ->forPerusahaan($perusahaan->id)
        ->buka()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($oracle)->toBe([$match->id])
        ->and($viaScope)->toBe($oracle);
});

it('scope belumSelesai returns the same ids as the inline where status_magang not selesai chain', function () {
    // Mirrors resources/views/pages/dosen/mahasiswa-bimbingan.blade.php:22
    // (->where('mahasiswa.status_magang', '!=', 'selesai')).
    //
    // NOTE: the stored enum value is 'selesai magang' (with a space), so the
    // inline `!= 'selesai'` chain is a no-op that returns EVERY row. The scope
    // must reproduce that exact (bug-for-bug) behaviour — parity, not intent.
    $aktif = Mahasiswa::factory()->create();
    $aktif->forceFill(['status_magang' => 'sedang magang'])->save();

    $belum = Mahasiswa::factory()->create();
    $belum->forceFill(['status_magang' => 'belum magang'])->save();

    $selesai = Mahasiswa::factory()->create();
    $selesai->forceFill(['status_magang' => 'selesai magang'])->save();

    $oracle = Mahasiswa::query()
        ->where('status_magang', '!=', 'selesai')
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $viaScope = Mahasiswa::query()
        ->belumSelesai()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    // The inline chain returns all three rows (see note above); parity holds.
    expect($oracle)->toEqualCanonicalizing([$aktif->id, $belum->id, $selesai->id])
        ->and($viaScope)->toBe($oracle);
});

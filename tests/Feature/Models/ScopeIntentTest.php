<?php

use App\Models\Mahasiswa;

/*
|--------------------------------------------------------------------------
| Scope intent (W2 / BUG5)
|--------------------------------------------------------------------------
|
| The `belumSelesai` scope answers "students whose internship is not yet
| finished". The stored enum value is 'selesai magang' (WITH a space), but the
| scope filtered on the bare 'selesai' — which never matched, so the filter was
| a no-op that returned EVERY row. This test pins the intended behaviour: a
| finished student must be excluded.
|
*/

beforeEach(function () {
    seedMasterData();
});

it('belumSelesai excludes students with status_magang selesai magang', function () {
    $aktif = Mahasiswa::factory()->create();
    $aktif->forceFill(['status_magang' => 'sedang magang'])->save();

    $belum = Mahasiswa::factory()->create();
    $belum->forceFill(['status_magang' => 'belum magang'])->save();

    $selesai = Mahasiswa::factory()->create();
    $selesai->forceFill(['status_magang' => 'selesai magang'])->save();

    $ids = Mahasiswa::query()
        ->belumSelesai()
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    // The finished student is EXCLUDED; the other two remain.
    expect($ids)->toEqualCanonicalizing([$aktif->id, $belum->id])
        ->and($ids)->not->toContain($selesai->id);
});

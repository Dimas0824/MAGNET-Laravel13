<?php

use App\Models\KontrakMagang;
use App\Models\Mahasiswa;

/**
 * P6-T4: kontrak_magang + mahasiswa soft-delete. A "delete" archives the row
 * (deleted_at set) so history survives; default queries exclude it, withTrashed
 * still sees it.
 */
beforeEach(function () {
    seedMasterData();
});

it('soft-deletes a kontrak so history survives', function () {
    $kontrak = KontrakMagang::factory()->create();

    $kontrak->delete();

    expect(KontrakMagang::find($kontrak->id))->toBeNull()
        ->and(KontrakMagang::withTrashed()->find($kontrak->id))->not->toBeNull()
        ->and(KontrakMagang::withTrashed()->find($kontrak->id)->deleted_at)->not->toBeNull();
});

it('soft-deletes a mahasiswa', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $mahasiswa->delete();

    expect(Mahasiswa::find($mahasiswa->id))->toBeNull()
        ->and(Mahasiswa::withTrashed()->find($mahasiswa->id))->not->toBeNull();
});

<?php

use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\Mahasiswa;
use Database\Seeders\KriteriaPreferensiSeeder;

/**
 * The preference seeder must be idempotent: running it twice (e.g. `db:seed`
 * re-run) must not accumulate a second criteria set per mahasiswa. The
 * criteria tables are unique on mahasiswa_id.
 */
beforeEach(function () {
    seedMasterData();
});

it('does not duplicate criteria rows when run twice', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    (new KriteriaPreferensiSeeder)->run();
    (new KriteriaPreferensiSeeder)->run();

    expect(KriteriaPekerjaan::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaBidangIndustri::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaLokasiMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaJenisMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaOpenRemote::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
});

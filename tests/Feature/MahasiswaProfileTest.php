<?php

use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

it('renders the mahasiswa profile page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    $this->get(route('profile'))->assertOk();
});

it('loads the criteria preferences without repeating the same relations', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('profile'))->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // The lookup tables joined for the criteria relations must not be re-read
    // once per access site (mount + loadCriteriaRankings + cancel path). The
    // dropdown-option plucks are separate and expected, so count only reads
    // that come from a join/where on the criteria tables.
    foreach (['pekerjaan', 'bidang_industri', 'lokasi_magang'] as $table) {
        $reads = $log->filter(function (array $entry) use ($table) {
            $sql = strtolower($entry['query']);

            return preg_match('/from `'.$table.'` where `id` in \(/', $sql) === 1;
        });

        expect($reads->count())->toBeLessThanOrEqual(1, "{$table} case read {$reads->count()} times");
    }
});

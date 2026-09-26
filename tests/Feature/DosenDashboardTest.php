<?php

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\LogMagang;
use App\Models\Mahasiswa;
use App\Models\UmpanBalikMagang;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

function dosenWithBimbingan(int $count = 3): DosenPembimbing
{
    $dosen = DosenPembimbing::factory()->create();

    for ($i = 0; $i < $count; $i++) {
        $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Bimbingan '.$i]);
        $lowongan = lowonganMagang();
        $kontrak = KontrakMagang::factory()->create([
            'mahasiswa_id' => $mahasiswa->id,
            'dosen_id' => $dosen->id,
            'lowongan_magang_id' => $lowongan->id,
        ]);

        LogMagang::create([
            'kontrak_magang_id' => $kontrak->id,
            'kegiatan' => 'Aktivitas harian '.$i,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '17:00:00',
        ]);

        UmpanBalikMagang::create([
            'kontrak_magang_id' => $kontrak->id,
            'komentar' => 'Feedback '.$i,
            'tanggal' => now()->toDateString(),
        ]);
    }

    return $dosen;
}

it('renders the dosen dashboard with bimbingan rows', function () {
    $dosen = dosenWithBimbingan(3);
    actingAsDosen($dosen);

    $this->get(route('dashboard'))->assertOk()->assertSee('Bimbingan 0');
});

it('does not run an N+1 of exists() queries per mahasiswa', function () {
    // Old code ran 2 exists() per bimbingan row (10+ for 5 rows) plus three
    // separate count() scans. Prove there is no N+1 by comparing the query
    // count at two sizes: constants (incl. the tenant-boot lookup) cancel out,
    // so a growing count means an N+1 regression.
    $countQueries = function (int $rows): int {
        $dosen = dosenWithBimbingan($rows);
        actingAsDosen($dosen);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $small = $countQueries(3);
    $large = $countQueries(9);

    expect($small)->toBeLessThanOrEqual(11)
        ->and($large)->toBe($small);
});

it('does not reference the dropped lowongan_magang.nama column', function () {
    $dosen = dosenWithBimbingan(2);
    actingAsDosen($dosen);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
    DB::disableQueryLog();

    expect($sql)->not->toContain('lowongan_magang`.`nama`');
});

it('reports the total and completed bimbingan counts from one grouped query', function () {
    $dosen = dosenWithBimbingan(4);
    actingAsDosen($dosen);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $grouped = $log->filter(function (array $entry) {
        $sql = strtolower($entry['query']);

        return str_contains($sql, 'kontrak_magang') && str_contains($sql, 'count(*)')
            && str_contains($sql, 'sum(case');
    });

    expect($grouped)->toHaveCount(1);
});

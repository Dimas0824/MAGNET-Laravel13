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
    $dosen = dosenWithBimbingan(5);
    actingAsDosen($dosen);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Old code: 2 exists() per bimbingan row (10+ for 5 rows) plus three
    // separate count() scans. With eager aggregates and one grouped stat query
    // it must stay bounded regardless of row count.
    expect($count)->toBeLessThanOrEqual(9);
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

<?php

use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

/**
 * Seed N recommendations with valid stage FK rows for a mahasiswa.
 */
function seedRecommendations(Mahasiswa $mahasiswa, int $count = 2): void
{
    $now = now();
    for ($i = 0; $i < $count; $i++) {
        $lowongan = lowonganMagang();

        $rs = RatioSystem::forceCreate([
            'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
            'score' => 10 - $i, 'rank' => $i + 1,
        ]);
        $rpId = DB::table('reference_point')->insertGetId([
            'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
            'pekerjaan' => 0, 'open_remote' => 0, 'jenis_magang' => 0,
            'bidang_industri' => 0, 'lokasi_magang' => 0, 'max_score' => 0.1 * ($i + 1),
            'rank' => $i + 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $fmf = FullMultiplicativeForm::forceCreate([
            'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
            'score' => 5 + $i, 'rank' => $i + 1,
        ]);

        FinalRankRecommendation::forceCreate([
            'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
            'ratio_system_id' => $rs->id, 'reference_point_id' => $rpId, 'fmf_id' => $fmf->id,
            'avg_rank' => $i + 1, 'rank' => $i + 1,
        ]);
    }
}

it('renders the dashboard for an authenticated mahasiswa', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedRecommendations($mahasiswa, 3);
    actingAsMahasiswa($mahasiswa);

    $this->get(route('dashboard'))->assertOk();
});

it('runs a bounded number of queries for the recommendations', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedRecommendations($mahasiswa, 5);
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A handful of queries (recommendations + preferences + lookups), not N+1.
    expect($queryCount)->toBeLessThanOrEqual(20);
});

it('does not reference the dropped lowongan_magang.nama column', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedRecommendations($mahasiswa, 2);
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
    DB::disableQueryLog();

    expect($queries)->not->toContain('lowongan_magang`.`nama`');
});

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
    // The recommendation page must not scale its query count with the number of
    // recommendations (no N+1). Prove it by comparing the count at two sizes:
    // the tenant-boot query and other constants cancel out, so a growing count
    // means an N+1 regression.
    $countQueries = function (int $rows): int {
        $mahasiswa = mahasiswaDenganPreferensi();
        seedRecommendations($mahasiswa, $rows);
        actingAsMahasiswa($mahasiswa);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $small = $countQueries(2);
    $large = $countQueries(8);

    // A handful of constant queries (recommendations + preferences + one tenant
    // boot lookup), and it must NOT grow with the row count.
    expect($small)->toBeLessThanOrEqual(16)
        ->and($large)->toBe($small);
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

it('resolves the preference labels from their lookup tables', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedRecommendations($mahasiswa, 2);
    actingAsMahasiswa($mahasiswa);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    // mahasiswaDenganPreferensi picks Software Engineer / Teknologi / paid
    // internship, all of which must still surface as resolved labels.
    $response->assertSee('Software Engineer', false);
    $response->assertSee('Teknologi', false);
    $response->assertSee('berbayar', false);
});

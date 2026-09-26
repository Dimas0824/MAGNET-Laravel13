<?php

use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

function seedFinalRanks(Mahasiswa $mahasiswa, int $count = 3): void
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
            'rank' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
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

it('renders the recommendation history page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedFinalRanks($mahasiswa, 3);
    actingAsMahasiswa($mahasiswa);

    $this->get(route('mahasiswa.riwayat-rekomendasi'))->assertOk();
});

it('computes the history without MySQL-only SQL functions', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedFinalRanks($mahasiswa, 3);
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('mahasiswa.riwayat-rekomendasi'))->assertOk();
    $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
    DB::disableQueryLog();

    expect($sql)->not->toContain('ROW_NUMBER')
        ->and($sql)->not->toContain('HOUR(')
        ->and($sql)->not->toContain('MINUTE(')
        ->and($sql)->not->toContain('LPAD(');
});

it('does not load the whole final_rank table to build the history page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    // Far more history than a single page should ever hydrate.
    seedFinalRanks($mahasiswa, 60);
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('mahasiswa.riwayat-rekomendasi'))->assertOk();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // Every query that reads final_rank_recommendation must be bounded by a
    // LIMIT (or be an aggregate) — never an unbounded row load.
    $unbounded = collect($log)->filter(function (array $entry) {
        $sql = strtolower($entry['query']);

        $readsTable = str_contains($sql, 'final_rank_recommendation');
        $unbounded = ! str_contains($sql, 'limit ');
        $isAggregate = str_contains($sql, 'count(') || str_contains($sql, 'max(') || str_contains($sql, 'min(') || str_contains($sql, 'sum(') || str_contains($sql, 'avg(');

        return $readsTable && $unbounded && ! $isAggregate;
    });

    expect($unbounded)->toBeEmpty(
        'History page issued an unbounded final_rank_recommendation query: '.
        $unbounded->pluck('query')->implode(' | ')
    );

    // And no single query returns more than a page worth of rows.
    $maxRows = collect($log)
        ->filter(fn (array $e) => str_contains($e['query'], 'final_rank_recommendation'))
        ->count();

    expect($maxRows)->toBeLessThanOrEqual(6);
});

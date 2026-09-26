<?php

use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

function seedDetailRecommendation(Mahasiswa $mahasiswa): void
{
    $now = now();
    $lowongan = lowonganMagang();

    $rs = RatioSystem::forceCreate([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
        'score' => 10, 'rank' => 1,
    ]);
    $rpId = DB::table('reference_point')->insertGetId([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
        'pekerjaan' => 0, 'open_remote' => 0, 'jenis_magang' => 0,
        'bidang_industri' => 0, 'lokasi_magang' => 0, 'max_score' => 0.1,
        'rank' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $fmf = FullMultiplicativeForm::forceCreate([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
        'score' => 5, 'rank' => 1,
    ]);

    FinalRankRecommendation::forceCreate([
        'mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $lowongan->id,
        'ratio_system_id' => $rs->id, 'reference_point_id' => $rpId, 'fmf_id' => $fmf->id,
        'avg_rank' => 1, 'rank' => 1,
    ]);
}

it('renders the recommendation detail page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedDetailRecommendation($mahasiswa);
    actingAsMahasiswa($mahasiswa);

    $this->get(route('mahasiswa.detail-rekomendasi'))->assertOk();
});

it('shows the open-remote preference value from the criteria relation', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    // mahasiswaDenganPreferensi sets open_remote preference to 'ya'.
    seedDetailRecommendation($mahasiswa);
    actingAsMahasiswa($mahasiswa);

    $response = $this->get(route('mahasiswa.detail-rekomendasi'));
    $response->assertOk();

    // The Open Remote row value cell must be exactly "Ya" (preference = 'ya').
    $response->assertSee('Open Remote', false);
    $response->assertSee('<td class="px-6 py-3">Ya', false);
});

it('does not load unbounded tables to render the calculation detail', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    seedDetailRecommendation($mahasiswa);
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('mahasiswa.detail-rekomendasi'))->assertOk();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    // A read is "unbounded" only if it can return the whole table: no LIMIT,
    // and no WHERE constraint at all. Eager loads of the form
    // `where id in (...)` are bounded by their parent row set and are fine.
    $unbounded = collect($log)->filter(function (array $entry) {
        $sql = strtolower($entry['query']);

        $readsBigTable = str_contains($sql, 'lowongan_magang')
            || str_contains($sql, 'final_rank_recommendation')
            || str_contains($sql, 'vector_normalization')
            || str_contains($sql, 'ratio_system')
            || str_contains($sql, 'reference_point')
            || str_contains($sql, 'full_multiplicative_form')
            || str_contains($sql, 'encoded_alternatives');

        $isAggregate = str_contains($sql, 'count(') || str_contains($sql, 'max(')
            || str_contains($sql, 'min(') || str_contains($sql, 'sum(') || str_contains($sql, 'avg(');

        $hasLimit = str_contains($sql, 'limit ');
        $hasWhere = str_contains($sql, ' where ');

        return $readsBigTable && ! $isAggregate && ! $hasLimit && ! $hasWhere;
    });

    expect($unbounded)->toBeEmpty(
        'Detail page issued unbounded reads: '.$unbounded->pluck('query')->implode(' | ')
    );
});

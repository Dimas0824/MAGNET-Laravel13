<?php

use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Helpers\DecisionMaking\MultiMOORA;
use App\Models\BidangIndustri;
use App\Models\EncodedAlternatives;
use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use App\Models\RatioSystem;
use App\Models\ReferencePoint;
use App\Models\VectorNormalization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    seedMasterData();
});

/**
 * Build a mahasiswa + N categorized openings, then run the full MULTIMOORA
 * pipeline. Returns [mahasiswa, lowonganCollection].
 */
function runPipeline(int $openingCount = 4): array
{
    $mahasiswa = mahasiswaDenganPreferensi();

    $lowongan = collect();
    for ($i = 0; $i < $openingCount; $i++) {
        $lowongan->push(lowonganMagang([
            'open_remote' => 'ya',
            'jenis_magang' => 'berbayar',
        ]));
    }

    foreach ($lowongan as $l) {
        DataPreprocessing::dataCategorization($l);
    }

    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    return [$mahasiswa, $lowongan];
}

it('produces vector normalization rows for every encoded alternative', function () {
    [$mahasiswa, $lowongan] = runPipeline(4);

    expect(VectorNormalization::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(4)
        ->and(EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(4);
});

it('produces ratio system, reference point and FMF rows with ranks 1..n', function () {
    [$mahasiswa] = runPipeline(4);

    foreach ([RatioSystem::class, ReferencePoint::class, FullMultiplicativeForm::class] as $model) {
        $ranks = $model::where('mahasiswa_id', $mahasiswa->id)->pluck('rank')->sort()->values()->all();
        expect($ranks)->toBe([1, 2, 3, 4]);
    }
});

it('produces a final rank for every opening with ranks 1..n', function () {
    [$mahasiswa] = runPipeline(4);

    $final = FinalRankRecommendation::where('mahasiswa_id', $mahasiswa->id)->get();

    expect($final)->toHaveCount(4)
        ->and($final->pluck('rank')->sort()->values()->all())->toBe([1, 2, 3, 4]);
});

it('ranks by ascending average of the three method ranks', function () {
    [$mahasiswa] = runPipeline(4);

    $avgRanks = FinalRankRecommendation::where('mahasiswa_id', $mahasiswa->id)
        ->orderBy('rank')->pluck('avg_rank')->map(fn ($v) => (float) $v)->all();

    $sorted = $avgRanks;
    sort($sorted);

    expect($avgRanks)->toEqual($sorted);
});

it('stores final_rank FK ids that point to the SAME lowongan as the row', function () {
    [$mahasiswa] = runPipeline(4);

    $finals = FinalRankRecommendation::with(['ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->where('mahasiswa_id', $mahasiswa->id)
        ->get();

    expect($finals)->not->toBeEmpty();

    foreach ($finals as $final) {
        expect($final->ratioSystem)->not->toBeNull()
            ->and($final->referencePoint)->not->toBeNull()
            ->and($final->fullMultiplicativeForm)->not->toBeNull();

        // The referenced stage rows must belong to the same lowongan & mahasiswa.
        expect($final->ratioSystem->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->referencePoint->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->fullMultiplicativeForm->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->ratioSystem->mahasiswa_id)->toBe($final->mahasiswa_id)
            ->and($final->referencePoint->mahasiswa_id)->toBe($final->mahasiswa_id)
            ->and($final->fullMultiplicativeForm->mahasiswa_id)->toBe($final->mahasiswa_id);
    }
});

it('is idempotent: rerunning with identical inputs replaces the run snapshot', function () {
    [$mahasiswa] = runPipeline(3);

    // Second run for the same mahasiswa with the SAME encoded inputs.
    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    $finals = FinalRankRecommendation::with(['ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->where('mahasiswa_id', $mahasiswa->id)
        ->get();

    // Same inputs => same recommendation_run => the snapshot is replaced, not
    // appended: 3 openings, one run (previously this appended a 2nd set of 3).
    expect($finals)->toHaveCount(3);

    foreach ($finals as $final) {
        expect($final->ratioSystem->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->referencePoint->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->fullMultiplicativeForm->lowongan_magang_id)->toBe($final->lowongan_magang_id);
    }
});

it('queries the lowongan count once per run', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    $lowongan = collect();
    for ($i = 0; $i < 4; $i++) {
        $lowongan->push(lowonganMagang([
            'open_remote' => 'ya',
            'jenis_magang' => 'berbayar',
        ]));
    }

    foreach ($lowongan as $l) {
        DataPreprocessing::dataCategorization($l);
    }

    DataPreprocessing::dataEncoding($mahasiswa);

    DB::enableQueryLog();
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    $countQueries = collect($log)->filter(function (array $entry) {
        $sql = strtolower($entry['query']);

        return str_contains($sql, 'count(*)') && str_contains($sql, 'from `lowongan_magang`');
    });

    expect($countQueries)->toHaveCount(1);
});

it('keeps FK integrity when the three methods rank alternatives differently', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    // A wide, varied set of openings so the weighted-sum (RS), weighted-product
    // (FMF) and Tchebycheff (RP) orderings genuinely diverge.
    $specs = [
        ['open_remote' => 'ya', 'jenis_magang' => 'berbayar', 'pekerjaan' => 'Software Engineer', 'bidang' => 'Teknologi'],
        ['open_remote' => 'ya', 'jenis_magang' => 'berbayar', 'pekerjaan' => 'Software Engineer', 'bidang' => 'Perbankan'],
        ['open_remote' => 'ya', 'jenis_magang' => 'berbayar', 'pekerjaan' => 'Data Engineer', 'bidang' => 'Teknologi'],
        ['open_remote' => 'tidak', 'jenis_magang' => 'berbayar', 'pekerjaan' => 'Software Engineer', 'bidang' => 'Teknologi'],
        ['open_remote' => 'ya', 'jenis_magang' => 'tidak berbayar', 'pekerjaan' => 'Software Engineer', 'bidang' => 'Teknologi'],
        ['open_remote' => 'ya', 'jenis_magang' => 'tidak berbayar', 'pekerjaan' => 'Data Engineer', 'bidang' => 'Kesehatan'],
        ['open_remote' => 'tidak', 'jenis_magang' => 'tidak berbayar', 'pekerjaan' => 'Data Engineer', 'bidang' => 'Teknologi'],
        ['open_remote' => 'tidak', 'jenis_magang' => 'tidak berbayar', 'pekerjaan' => 'UI/UX Designer', 'bidang' => 'Perbankan'],
    ];

    $lowongan = collect();
    foreach ($specs as $spec) {
        $perusahaan = Perusahaan::factory()->create([
            'bidang_industri_id' => BidangIndustri::where('nama', $spec['bidang'])->value('id'),
        ]);
        $lowongan->push(lowonganMagang([
            'open_remote' => $spec['open_remote'],
            'jenis_magang' => $spec['jenis_magang'],
            'pekerjaan_id' => Pekerjaan::where('nama', $spec['pekerjaan'])->value('id'),
            'perusahaan_id' => $perusahaan->id,
        ]));
    }

    foreach ($lowongan as $l) {
        DataPreprocessing::dataCategorization($l);
    }
    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    $finals = FinalRankRecommendation::with(['ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->where('mahasiswa_id', $mahasiswa->id)
        ->get();

    expect($finals)->toHaveCount(count($specs));

    foreach ($finals as $final) {
        expect($final->ratioSystem->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->referencePoint->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->fullMultiplicativeForm->lowongan_magang_id)->toBe($final->lowongan_magang_id);
    }
});

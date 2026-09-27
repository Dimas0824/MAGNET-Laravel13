<?php

use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Helpers\DecisionMaking\MultiMOORA;
use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\RatioSystem;
use App\Models\RecommendationRun;
use App\Models\ReferencePoint;
use App\Models\VectorNormalization;
use Illuminate\Support\Facades\Storage;

/**
 * The pipeline must be idempotent: running it twice for the same mahasiswa and
 * the same encoded inputs must NOT append a second snapshot of stage rows.
 *
 * Design: each computation is a `recommendation_run` keyed by
 * (mahasiswa_id, run_key). Same inputs => same run_key => firstOrCreate returns
 * the existing run and the recompute is a no-op. Different inputs create a new
 * run, which is how history is preserved (one group per run).
 */
beforeEach(function () {
    Storage::fake('local');
    seedMasterData();
});

/**
 * Run the pipeline end-to-end for a fresh mahasiswa with $openingCount openings.
 * Returns [mahasiswa, openingIds].
 */
function rerunPipeline(int $openingCount = 3): array
{
    $mahasiswa = mahasiswaDenganPreferensi();

    $lowongan = collect();
    for ($i = 0; $i < $openingCount; $i++) {
        $lowongan->push(lowonganMagang(['open_remote' => 'ya', 'jenis_magang' => 'berbayar']));
    }

    foreach ($lowongan as $l) {
        DataPreprocessing::dataCategorization($l);
    }

    return [$mahasiswa, $lowongan];
}

it('creates exactly one recommendation_run for a first computation', function () {
    [$mahasiswa] = rerunPipeline(3);

    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    expect(RecommendationRun::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
});

it('does not duplicate stage rows when the pipeline runs twice with identical inputs', function () {
    [$mahasiswa] = rerunPipeline(3);

    // First run.
    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    $firstRun = RecommendationRun::where('mahasiswa_id', $mahasiswa->id)->sole();

    // Second run, same encoded inputs -> same run_key -> must be a no-op.
    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    // Still exactly one run.
    expect(RecommendationRun::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);

    // Stage tables hold exactly one snapshot (3 openings), not two (6).
    expect(VectorNormalization::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(3)
        ->and(RatioSystem::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(3)
        ->and(ReferencePoint::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(3)
        ->and(FullMultiplicativeForm::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(3)
        ->and(FinalRankRecommendation::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(3);

    // Every stage row is stamped with that single run id.
    expect(VectorNormalization::where('mahasiswa_id', $mahasiswa->id)->where('run_id', $firstRun->id)->count())->toBe(3)
        ->and(FinalRankRecommendation::where('mahasiswa_id', $mahasiswa->id)->where('run_id', $firstRun->id)->count())->toBe(3);
});

it('creates a new run (preserving history) when the inputs change', function () {
    [$mahasiswa] = rerunPipeline(3);

    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    // Add a 4th opening -> different encoded set -> different run_key.
    $extra = lowonganMagang(['open_remote' => 'ya', 'jenis_magang' => 'berbayar']);
    DataPreprocessing::dataCategorization($extra);

    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    // Two distinct runs now exist.
    expect(RecommendationRun::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(2);

    // History: the newest run has 4 final rows; the older run's 3 are still there.
    $runs = RecommendationRun::where('mahasiswa_id', $mahasiswa->id)->orderBy('id')->get();
    expect(FinalRankRecommendation::where('run_id', $runs[0]->id)->count())->toBe(3)
        ->and(FinalRankRecommendation::where('run_id', $runs[1]->id)->count())->toBe(4);
});

it('writes final rank rows whose stage FKs all belong to the same run and lowongan', function () {
    [$mahasiswa] = rerunPipeline(4);

    DataPreprocessing::dataEncoding($mahasiswa);
    (new MultiMOORA($mahasiswa))->computeMultiMOORA();

    $finals = FinalRankRecommendation::with(['ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->where('mahasiswa_id', $mahasiswa->id)
        ->get();

    expect($finals)->toHaveCount(4);

    foreach ($finals as $final) {
        expect($final->ratioSystem->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->referencePoint->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->fullMultiplicativeForm->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            // All three stage rows must belong to the run the final row references.
            ->and($final->ratioSystem->run_id)->toBe($final->run_id)
            ->and($final->referencePoint->run_id)->toBe($final->run_id)
            ->and($final->fullMultiplicativeForm->run_id)->toBe($final->run_id);
    }
});

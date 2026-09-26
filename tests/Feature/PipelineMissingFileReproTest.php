<?php

use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Models\EncodedAlternatives;
use App\Models\LowonganMagang;
use Illuminate\Support\Facades\Storage;

/**
 * Root cause of the reported RunRecommendationPipeline production failure
 * (3x FAIL in the queue worker, observed 2026-09-23).
 *
 * The suite normally runs with Storage::fake('local') plus a pre-categorized
 * file, so the crash never surfaced. In production the worker starts with an
 * empty app_storage volume, so alternatives_categorized.json does not exist.
 * DataPreprocessing::dataEncoding() then does:
 *
 *     $dataCategorized = Storage::json($path);                 // null (missing file)
 *     array_map(fn (array $item) => [...], $dataCategorized);  // TypeError
 *
 * which throws and, with $tries = 3, produces exactly the observed 3 failures.
 *
 * This test uses the REAL local disk (no Storage::fake) so Storage::json()
 * genuinely returns null, matching the production worker.
 */
beforeEach(function () {
    seedMasterData();
});

it('encodes safely when the categorized alternatives file does not exist yet', function () {
    $path = config('recommendation-system.preprocessing.alternatives_categorized_path');
    Storage::disk('local')->delete($path);

    expect(Storage::disk('local')->exists($path))->toBeFalse();

    $mahasiswa = mahasiswaDenganPreferensi();

    // Must not throw: an empty alternative set is a valid state (no openings
    // categorized yet). The pipeline should simply produce zero encodings.
    DataPreprocessing::dataEncoding($mahasiswa);

    expect(EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(0);
});

it('encodes the categorized openings when the file exists', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    $opening = LowonganMagang::withoutEvents(fn () => lowonganMagang());
    DataPreprocessing::dataCategorization($opening);

    DataPreprocessing::dataEncoding($mahasiswa);

    expect(EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
});

it('does not duplicate encodings when the pipeline runs twice for the same mahasiswa', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    $opening = LowonganMagang::withoutEvents(fn () => lowonganMagang());
    DataPreprocessing::dataCategorization($opening);

    DataPreprocessing::dataEncoding($mahasiswa);
    DataPreprocessing::dataEncoding($mahasiswa);

    expect(EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
});

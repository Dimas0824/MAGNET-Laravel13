<?php

use App\Helpers\DecisionMaking\MultiMOORA;
use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use App\Models\ReferencePoint;

/**
 * White-box test for MultiMOORA::computeFinalRank FK mapping.
 *
 * It seeds the three stage tables directly with deliberately divergent
 * orderings (ratio-system rank order != final average-rank order) and then
 * drives computeFinalRank via reflection. This isolates the FK-mapping logic
 * from the rest of the pipeline.
 */
beforeEach(function () {
    seedMasterData();
});

function invokeComputeFinalRank(Mahasiswa $mahasiswa): array
{
    $ref = new ReflectionClass(MultiMOORA::class);
    $instance = $ref->newInstanceWithoutConstructor();

    $mahasiswaProp = $ref->getProperty('mahasiswa');
    $mahasiswaProp->setAccessible(true);
    $mahasiswaProp->setValue($instance, $mahasiswa);

    $now = now();
    $nowProp = $ref->getProperty('now');
    $nowProp->setAccessible(true);
    $nowProp->setValue($instance, $now);

    // Three real openings A, B, C (FK-safe).
    $a = lowonganMagang()->id;
    $b = lowonganMagang()->id;
    $c = lowonganMagang()->id;

    // Ratio-system insertion order (by id): A, B, C with ranks 2, 3, 1.
    // Reference-point insertion order:      C, A, B with ranks 1, 2, 3.
    // FMF insertion order:                  B, C, A with ranks 3, 1, 2.
    // Final combined order (by avg_rank asc):
    //   A: (2+2+3)/3 = 2.333
    //   B: (3+3+1)/3 = 2.333
    //   C: (1+1+2)/3 = 1.333  -> C first
    // So the final order (C, then A/B) differs from every stage insertion order.
    RatioSystem::insert([
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $a, 'score' => 10, 'rank' => 2, 'created_at' => $now, 'updated_at' => $now],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $b, 'score' => 9, 'rank' => 3, 'created_at' => $now, 'updated_at' => $now],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $c, 'score' => 11, 'rank' => 1, 'created_at' => $now, 'updated_at' => $now],
    ]);

    ReferencePoint::insert([
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $c, 'pekerjaan' => 0, 'open_remote' => 0, 'jenis_magang' => 0, 'bidang_industri' => 0, 'lokasi_magang' => 0, 'max_score' => 0.1, 'rank' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $a, 'pekerjaan' => 0, 'open_remote' => 0, 'jenis_magang' => 0, 'bidang_industri' => 0, 'lokasi_magang' => 0, 'max_score' => 0.2, 'rank' => 2, 'created_at' => $now, 'updated_at' => $now],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $b, 'pekerjaan' => 0, 'open_remote' => 0, 'jenis_magang' => 0, 'bidang_industri' => 0, 'lokasi_magang' => 0, 'max_score' => 0.3, 'rank' => 3, 'created_at' => $now, 'updated_at' => $now],
    ]);

    FullMultiplicativeForm::insert([
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $b, 'score' => 5, 'rank' => 3, 'created_at' => $now, 'updated_at' => $now],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $c, 'score' => 8, 'rank' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $a, 'score' => 6, 'rank' => 2, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $ratioRef = $ref->getProperty('ratioSystemResult');
    $ratioRef->setAccessible(true);
    $ratioRef->setValue($instance, [
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $a, 'score' => 10, 'rank' => 2],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $b, 'score' => 9, 'rank' => 3],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $c, 'score' => 11, 'rank' => 1],
    ]);
    $rpRef = $ref->getProperty('referencePointResult');
    $rpRef->setAccessible(true);
    $rpRef->setValue($instance, [
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $c, 'rank' => 1],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $a, 'rank' => 2],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $b, 'rank' => 3],
    ]);
    $fmfRef = $ref->getProperty('fmfResult');
    $fmfRef->setAccessible(true);
    $fmfRef->setValue($instance, [
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $b, 'score' => 5, 'rank' => 3],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $c, 'score' => 8, 'rank' => 1],
        ['mahasiswa_id' => $mahasiswa->id, 'lowongan_magang_id' => $a, 'score' => 6, 'rank' => 2],
    ]);

    $method = $ref->getMethod('computeFinalRank');
    $method->setAccessible(true);
    $method->invoke($instance);

    return ['a' => $a, 'b' => $b, 'c' => $c];
}

it('maps final_rank FK ids to the correct lowongan even when stage orders differ', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $ids = invokeComputeFinalRank($mahasiswa);

    $finals = FinalRankRecommendation::with(['ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->where('mahasiswa_id', $mahasiswa->id)
        ->get();

    expect($finals)->toHaveCount(3);

    // Best rank must be opening C.
    $best = $finals->firstWhere('rank', 1);
    expect($best->lowongan_magang_id)->toBe($ids['c']);

    foreach ($finals as $final) {
        expect($final->ratioSystem->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->referencePoint->lowongan_magang_id)->toBe($final->lowongan_magang_id)
            ->and($final->fullMultiplicativeForm->lowongan_magang_id)->toBe($final->lowongan_magang_id);
    }
});

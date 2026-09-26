<?php

namespace App\Helpers\DecisionMaking;

use App\Models\EncodedAlternatives;
use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use App\Models\RecommendationRun;
use App\Models\ReferencePoint;
use App\Models\VectorNormalization;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MultiMOORA
{
    private Carbon $now;

    private Mahasiswa $mahasiswa;

    private KriteriaPekerjaan $kriteriaPekerjaan;

    private KriteriaOpenRemote $kriteriaOpenRemote;

    private KriteriaBidangIndustri $kriteriaBidangIndustri;

    private KriteriaJenisMagang $kriteriaJenisMagang;

    private KriteriaLokasiMagang $kriteriaLokasiMagang;

    /**
     * Number of available lowongan magang, resolved once per instance so the
     * (potentially expensive) aggregate count is not re-issued for every stage
     * of the pipeline.
     */
    private ?int $lowonganCount = null;

    private array $encodedAlternatives;

    /**
     * The recommendation_run this computation belongs to. Set by resolveRun().
     */
    private ?int $runId = null;

    /**
     * @var array<array{
     *  id: int,
     *  pekerjaan: float,
     *  open_remote: float,
     *  jenis_magang: float,
     *  bidang_industri: float,
     *  lokasi_magang: float
     * }>
     */
    private array $vectorNormalizationResult;

    /**
     * @var array<array{
     *  id: int,
     *  score: float,
     *  rank: int
     * }>
     */
    private array $ratioSystemResult;

    /**
     * @var array<array{
     *  id: int,
     *  pekerjaan: float,
     *  open_remote: float,
     *  jenis_magang: float,
     *  bidang_industri: float,
     *  lokasi_magang: float,
     *  max_score: float,
     *  rank: int
     * }>
     */
    private array $referencePointResult;

    /**
     * @var array<array{
     *  id: int,
     *  score: float,
     *  rank: int
     * }>
     */
    private array $fmfResult;

    public function __construct(Mahasiswa $mahasiswa, ?array $dataEncoding = null)
    {
        $this->mahasiswa = $mahasiswa;
        $this->lowonganCount = $this->lowonganCount();

        if ($dataEncoding) {
            $this->encodedAlternatives = $dataEncoding;
        } else {
            $this->encodedAlternatives = EncodedAlternatives::where('mahasiswa_id', $this->mahasiswa->id)
                ->orderBy('updated_at', 'desc')
                ->limit($this->lowonganCount)
                ->get()
                ->toArray();
        }

        // Eager-load all five criteria in one query instead of five lazy loads.
        $this->mahasiswa->load([
            'kriteriaPekerjaan',
            'kriteriaOpenRemote',
            'kriteriaBidangIndustri',
            'kriteriaJenisMagang',
            'kriteriaLokasiMagang',
        ]);

        $this->kriteriaPekerjaan = $this->mahasiswa->kriteriaPekerjaan;
        $this->kriteriaOpenRemote = $this->mahasiswa->kriteriaOpenRemote;
        $this->kriteriaBidangIndustri = $this->mahasiswa->kriteriaBidangIndustri;
        $this->kriteriaJenisMagang = $this->mahasiswa->kriteriaJenisMagang;
        $this->kriteriaLokasiMagang = $this->mahasiswa->kriteriaLokasiMagang;
    }

    /**
     * Lazily resolve and memoize the lowongan count so a single pipeline run
     * issues exactly one aggregate query, while still working when the
     * constructor is bypassed (e.g. white-box tests using
     * newInstanceWithoutConstructor()).
     */
    private function lowonganCount(): int
    {
        return $this->lowonganCount ??= LowonganMagang::count();
    }

    /**
     * Resolve (create or reuse) the recommendation_run for this computation.
     *
     * The run_key is a deterministic fingerprint of the inputs, so a repeated
     * run (event re-fire, queued retry) maps to the SAME run row and its stage
     * rows are replaced in place — no duplicated snapshot. A materially
     * different input set yields a new run, which is how history is kept.
     */
    private function resolveRun(): RecommendationRun
    {
        $weights = [
            'pekerjaan' => $this->kriteriaPekerjaan->bobot,
            'open_remote' => $this->kriteriaOpenRemote->bobot,
            'bidang_industri' => $this->kriteriaBidangIndustri->bobot,
            'jenis_magang' => $this->kriteriaJenisMagang->bobot,
            'lokasi_magang' => $this->kriteriaLokasiMagang->bobot,
        ];

        $runKey = RecommendationRun::makeKey($this->mahasiswa->id, $this->encodedAlternatives, $weights);

        $run = RecommendationRun::firstOrCreate(
            ['mahasiswa_id' => $this->mahasiswa->id, 'run_key' => $runKey],
            ['status' => RecommendationRun::STATUS_RUNNING, 'opening_count' => count($this->encodedAlternatives)]
        );

        $this->runId = $run->id;

        return $run;
    }

    /**
     * Remove any stage rows already written for this run so a re-computation
     * replaces the run's snapshot instead of appending a second one. Scoped to
     * the run id, so other runs' history is untouched.
     */
    private function clearRunStages(): void
    {
        if ($this->runId === null) {
            return;
        }

        VectorNormalization::where('run_id', $this->runId)->delete();
        RatioSystem::where('run_id', $this->runId)->delete();
        ReferencePoint::where('run_id', $this->runId)->delete();
        FullMultiplicativeForm::where('run_id', $this->runId)->delete();
        FinalRankRecommendation::where('run_id', $this->runId)->delete();
    }

    public function computeMultiMOORA(): void
    {
        $this->now = now();

        $run = $this->resolveRun();

        // One outer transaction: encoding + every stage commit together, so a
        // failure mid-pipeline cannot leave a partial snapshot behind.
        DB::transaction(function () {
            $this->clearRunStages();

            $euclideanNormalizationResult = $this->euclideanNormalization($this->encodedAlternatives);

            $this->vectorNormalization(
                encodedAlternatives: $this->encodedAlternatives,
                euclideanNormalization: $euclideanNormalizationResult
            );
            $this->computeRatioSystem();
            $this->computeReferencePoint();
            $this->computeFullMultiplicativeForm();
            $this->computeFinalRank();
        });

        $run->update(['status' => RecommendationRun::STATUS_DONE]);
    }

    /**
     * Compute euclidean normalization
     *
     * @return array<string, int[]>
     */
    private function euclideanNormalization(array $encodedAlternatives): array
    {
        $pekerjaanList = collect($encodedAlternatives)->pluck('pekerjaan')->all();
        $bidangIndustriList = collect($encodedAlternatives)->pluck('bidang_industri')->all();
        $jenisMagangList = collect($encodedAlternatives)->pluck('jenis_magang')->all();
        $lokasiMagangList = collect($encodedAlternatives)->pluck('lokasi_magang')->all();
        $openRemoteList = collect($encodedAlternatives)->pluck('open_remote')->all();

        $computeEuclidean = function (array $list) {
            $sumSquares = 0.0;

            foreach ($list as $item) {
                $sumSquares += pow($item, 2);
            }

            return sqrt($sumSquares);
        };

        $listOfCriterias = [
            'pekerjaan' => $pekerjaanList,
            'bidang_industri' => $bidangIndustriList,
            'lokasi_magang' => $lokasiMagangList,
            'open_remote' => $openRemoteList,
            'jenis_magang' => $jenisMagangList,
        ];

        $euclideanNormalizationList = [];
        foreach ($listOfCriterias as $criteria => $list) {
            $euclideanNormalizationList[$criteria] = $computeEuclidean($list);
        }

        return $euclideanNormalizationList;
    }

    /**
     * Compute vector normalization for all alternatives data
     */
    private function vectorNormalization(array $encodedAlternatives, array $euclideanNormalization): void
    {
        $pekerjaanList = collect($encodedAlternatives)->pluck('pekerjaan')->all();
        $bidangIndustriList = collect($encodedAlternatives)->pluck('bidang_industri')->all();
        $jenisMagangList = collect($encodedAlternatives)->pluck('jenis_magang')->all();
        $lokasiMagangList = collect($encodedAlternatives)->pluck('lokasi_magang')->all();
        $openRemoteList = collect($encodedAlternatives)->pluck('open_remote')->all();

        $listOfCriterias = [
            'pekerjaan' => $pekerjaanList,
            'bidang_industri' => $bidangIndustriList,
            'lokasi_magang' => $lokasiMagangList,
            'open_remote' => $openRemoteList,
            'jenis_magang' => $jenisMagangList,
        ];

        /**
         * Computes the vector normalization for a given criteria using Euclidean normalization.
         *
         * This function takes a specific criteria and a list of values, then normalizes each value
         * by dividing it with the corresponding Euclidean normalization value for that criteria.
         *
         * @param  string  $criteria  The key used to access the corresponding normalization value.
         * @param  array  $list  An array of numeric values to normalize.
         * @return array An array of normalized values.
         */
        $computeVectorNormalization = function (string $criteria, array $list) use ($euclideanNormalization): array {
            $result = [];
            foreach ($list as $item) {
                $result[] = $item / $euclideanNormalization[$criteria];
            }

            return $result;
        };

        $tempResult = [];
        foreach ($listOfCriterias as $criteria => $list) {
            $tempResult[$criteria] = $computeVectorNormalization($criteria, $list);
        }

        $finalVectorNormalization = [];
        for ($i = 0; $i < count($encodedAlternatives); $i++) {
            $finalVectorNormalization[] = [
                'mahasiswa_id' => $this->mahasiswa->id,
                'lowongan_magang_id' => $encodedAlternatives[$i]['lowongan_magang_id'],
                'pekerjaan' => $tempResult['pekerjaan'][$i],
                'open_remote' => $tempResult['open_remote'][$i],
                'jenis_magang' => $tempResult['jenis_magang'][$i],
                'bidang_industri' => $tempResult['bidang_industri'][$i],
                'lokasi_magang' => $tempResult['lokasi_magang'][$i],
            ];
        }

        $resultToSavedDatabase = [];
        for ($i = 0; $i < count($encodedAlternatives); $i++) {
            $resultToSavedDatabase[] = [
                'run_id' => $this->runId,
                'mahasiswa_id' => $this->mahasiswa->id,
                'lowongan_magang_id' => $encodedAlternatives[$i]['lowongan_magang_id'],
                'pekerjaan' => $tempResult['pekerjaan'][$i],
                'open_remote' => $tempResult['open_remote'][$i],
                'jenis_magang' => $tempResult['jenis_magang'][$i],
                'bidang_industri' => $tempResult['bidang_industri'][$i],
                'lokasi_magang' => $tempResult['lokasi_magang'][$i],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        VectorNormalization::insert($resultToSavedDatabase);

        $this->vectorNormalizationResult = $finalVectorNormalization;
    }

    /**
     * Compute ratio system. The result of this computation will be place in the MultiMOORA object attribute
     */
    private function computeRatioSystem(): void
    {
        $weights = [
            'pekerjaan' => $this->kriteriaPekerjaan->bobot,
            'open_remote' => $this->kriteriaOpenRemote->bobot,
            'bidang_industri' => $this->kriteriaBidangIndustri->bobot,
            'jenis_magang' => $this->kriteriaJenisMagang->bobot,
            'lokasi_magang' => $this->kriteriaLokasiMagang->bobot,
        ];

        $ratioSystemResult = array_map(function (array $alt) use ($weights) {
            return [
                'lowongan_magang_id' => $alt['lowongan_magang_id'],
                'score' => array_sum([
                    $alt['pekerjaan'] * $weights['pekerjaan'],
                    $alt['open_remote'] * $weights['open_remote'],
                    $alt['bidang_industri'] * $weights['bidang_industri'],
                    $alt['jenis_magang'] * $weights['jenis_magang'],
                    $alt['lokasi_magang'] * $weights['lokasi_magang'],
                ]),
            ];
        }, $this->vectorNormalizationResult);

        usort($ratioSystemResult, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $ratioSystemRank = [];
        $rank = 1;
        foreach ($ratioSystemResult as $item) {
            $ratioSystemRank[] = [
                'run_id' => $this->runId,
                'mahasiswa_id' => $this->mahasiswa->id,
                'lowongan_magang_id' => $item['lowongan_magang_id'],
                'score' => $item['score'],
                'rank' => $rank++,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        RatioSystem::insert($ratioSystemRank);

        $this->ratioSystemResult = $ratioSystemRank;
    }

    /**
     * Compute reference point. The result of this computation will be place in the MultiMOORA object attribute
     */
    private function computeReferencePoint(): void
    {
        $weights = [
            'pekerjaan' => $this->kriteriaPekerjaan->bobot,
            'open_remote' => $this->kriteriaOpenRemote->bobot,
            'bidang_industri' => $this->kriteriaBidangIndustri->bobot,
            'jenis_magang' => $this->kriteriaJenisMagang->bobot,
            'lokasi_magang' => $this->kriteriaLokasiMagang->bobot,
        ];

        $referencePointVector = [
            'pekerjaan' => 0.0,
            'open_remote' => 0.0,
            'bidang_industri' => 0.0,
            'jenis_magang' => 0.0,
            'lokasi_magang' => 0.0,
        ];

        // Find the maximum value for each criterion across all alternatives.
        foreach ($this->vectorNormalizationResult as $alt) {
            foreach (array_keys($referencePointVector) as $criteria) {
                if ($alt[$criteria] > $referencePointVector[$criteria]) {
                    $referencePointVector[$criteria] = $alt[$criteria];
                }
            }
        }

        // Step 2 & 3: Calculate the maximum deviation for each alternative using the Tchebycheff Min-Max method.
        $deviationScores = array_map(function (array $alt) use ($referencePointVector, $weights) {
            // Calculate the weighted deviation from the reference point for each criterion.
            $pekerjaanDev = $weights['pekerjaan'] * abs($referencePointVector['pekerjaan'] - $alt['pekerjaan']);
            $openRemoteDev = $weights['open_remote'] * abs($referencePointVector['open_remote'] - $alt['open_remote']);
            $jenisMagangDev = $weights['jenis_magang'] * abs($referencePointVector['jenis_magang'] - $alt['jenis_magang']);
            $bidangIndustriDev = $weights['bidang_industri'] * abs($referencePointVector['bidang_industri'] - $alt['bidang_industri']);
            $lokasiMagangDev = $weights['lokasi_magang'] * abs($referencePointVector['lokasi_magang'] - $alt['lokasi_magang']);

            // Find the maximal deviation (regret) for the current alternative.
            $maxDeviation = max($pekerjaanDev, $openRemoteDev, $jenisMagangDev, $bidangIndustriDev, $lokasiMagangDev);

            return [
                'lowongan_magang_id' => $alt['lowongan_magang_id'],
                'pekerjaan' => $pekerjaanDev,
                'open_remote' => $openRemoteDev,
                'jenis_magang' => $jenisMagangDev,
                'bidang_industri' => $bidangIndustriDev,
                'lokasi_magang' => $lokasiMagangDev,
                'max_score' => $maxDeviation,
            ];
        }, $this->vectorNormalizationResult);

        // Step 4: Rank the alternatives based on max_score in ASCENDING order.
        // The smaller the max_score, the better the rank.
        usort($deviationScores, function ($a, $b) {
            return $a['max_score'] <=> $b['max_score'];
        });

        $referencePointFinalResult = [];
        $rank = 1;
        foreach ($deviationScores as $item) {
            $referencePointFinalResult[] = [
                'run_id' => $this->runId,
                'mahasiswa_id' => $this->mahasiswa->id,
                'lowongan_magang_id' => $item['lowongan_magang_id'],
                'pekerjaan' => $item['pekerjaan'],
                'open_remote' => $item['open_remote'],
                'jenis_magang' => $item['jenis_magang'],
                'bidang_industri' => $item['bidang_industri'],
                'lokasi_magang' => $item['lokasi_magang'],
                'max_score' => $item['max_score'],
                'rank' => $rank++,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        ReferencePoint::insert($referencePointFinalResult);

        $this->referencePointResult = $referencePointFinalResult;
    }

    /**
     * Compute Full Multiplicative Form (FMF). The result of this computation will be place in the MultiMOORA object attribute
     */
    private function computeFullMultiplicativeForm(): void
    {
        $weights = [
            'pekerjaan' => $this->kriteriaPekerjaan->bobot,
            'open_remote' => $this->kriteriaOpenRemote->bobot,
            'bidang_industri' => $this->kriteriaBidangIndustri->bobot,
            'jenis_magang' => $this->kriteriaJenisMagang->bobot,
            'lokasi_magang' => $this->kriteriaLokasiMagang->bobot,
        ];

        $fmfScores = array_map(function (array $alt) use ($weights): array {
            $scores = [
                pow($alt['pekerjaan'], $weights['pekerjaan']),
                pow($alt['open_remote'], $weights['open_remote']),
                pow($alt['bidang_industri'], $weights['bidang_industri']),
                pow($alt['jenis_magang'], $weights['jenis_magang']),
                pow($alt['lokasi_magang'], $weights['lokasi_magang']),
            ];

            return [
                'lowongan_magang_id' => $alt['lowongan_magang_id'],
                'score' => array_reduce($scores, fn ($carry, $item) => $carry * $item, 1),
            ];
        }, $this->vectorNormalizationResult);

        // ranking process (descending)
        usort($fmfScores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $fmfFinalRanks = [];
        $rank = 1;
        foreach ($fmfScores as $item) {
            $fmfFinalRanks[] = [
                'run_id' => $this->runId,
                'mahasiswa_id' => $this->mahasiswa->id,
                'lowongan_magang_id' => $item['lowongan_magang_id'],
                'score' => $item['score'],
                'rank' => $rank++,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        FullMultiplicativeForm::insert($fmfFinalRanks);

        $this->fmfResult = $fmfFinalRanks;
    }

    /**
     * Compute final rank of alternatives with MultiMOORA method
     *
     * @return array
     */
    private function computeFinalRank(): void
    {
        $ratioSystemRanks = array_column($this->ratioSystemResult, 'rank', 'lowongan_magang_id');
        $referencePointRanks = array_column($this->referencePointResult, 'rank', 'lowongan_magang_id');
        $fmfRanks = array_column($this->fmfResult, 'rank', 'lowongan_magang_id');

        // collect all IDs
        $allIDs = array_unique(
            array_merge(
                array_keys($ratioSystemRanks),
                array_keys($referencePointRanks),
                array_keys($fmfRanks)
            )
        );

        // combine all array into a single array based on ID
        $combinedArray = [];
        foreach ($allIDs as $id) {
            // A criterion that produced no rank for this alternative is treated
            // as rank 0 so the average never dereferences a missing key.
            $avgRank = array_sum([
                $ratioSystemRanks[$id] ?? 0,
                $referencePointRanks[$id] ?? 0,
                $fmfRanks[$id] ?? 0,
            ]) / 3;
            $combinedArray[] = [
                'lowongan_magang_id' => $id,
                'ratio_system_rank' => $ratioSystemRanks[$id] ?? null,
                'reference_point_rank' => $referencePointRanks[$id] ?? null,
                'fmf_rank' => $fmfRanks[$id] ?? null,
                'avg_rank' => $avgRank,
            ];
        }

        // ranking process (ascending)
        usort($combinedArray, function ($a, $b) {
            return $a['avg_rank'] <=> $b['avg_rank'];
        });

        // Map each stage row's id by lowongan_magang_id, scoped to THIS run.
        // Previously this read "newest N by id desc", which mis-mapped rows when
        // the opening set changed between runs (the limit slid off the wrong
        // rows) and broke under concurrent runs. Keying on run_id is exact.
        $ratioSystemIDs = RatioSystem::where('run_id', $this->runId)
            ->pluck('id', 'lowongan_magang_id')
            ->all();

        $referencePointIDs = ReferencePoint::where('run_id', $this->runId)
            ->pluck('id', 'lowongan_magang_id')
            ->all();

        $fmfIDs = FullMultiplicativeForm::where('run_id', $this->runId)
            ->pluck('id', 'lowongan_magang_id')
            ->all();

        $finalRanks = [];
        $rank = 1;
        foreach ($combinedArray as $item) {
            $lowonganId = $item['lowongan_magang_id'];

            $finalRanks[] = [
                'run_id' => $this->runId,
                'mahasiswa_id' => $this->mahasiswa->id,
                'lowongan_magang_id' => $lowonganId,
                'ratio_system_id' => $ratioSystemIDs[$lowonganId] ?? null,
                'reference_point_id' => $referencePointIDs[$lowonganId] ?? null,
                'fmf_id' => $fmfIDs[$lowonganId] ?? null,
                'avg_rank' => $item['avg_rank'],
                'rank' => $rank++,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        FinalRankRecommendation::insert($finalRanks);
    }
}

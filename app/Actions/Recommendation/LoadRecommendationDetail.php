<?php

namespace App\Actions\Recommendation;

use App\Actions\Action;
use App\Models\EncodedAlternatives;
use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use App\Models\ReferencePoint;
use App\Models\VectorNormalization;
use Illuminate\Support\Collection;

/**
 * Load every dataset the mahasiswa "detail rekomendasi" page renders.
 *
 * Extracted verbatim from
 * resources/views/pages/mahasiswa/riwayat-rekomendasi/detail-rekomendasi.blade.php:
 * the previous mount() body plus the $get* closures. Returns the exact same
 * array shape the Volt view bound via state(), so rendering stays identical.
 */
class LoadRecommendationDetail implements Action
{
    public function __construct(
        private readonly ?Mahasiswa $mahasiswa,
        private readonly ?string $tanggal = null,
    ) {}

    /**
     * @return array{
     *     mahasiswa: ?Mahasiswa,
     *     rankingKriteria: array<int, array{nama: string, ranking: mixed, bobot: mixed}>,
     *     alternatifLowongan: Collection,
     *     numericTable: Collection,
     *     normalisasiEuclidean: array<string, float|int>,
     *     normalisasiVektor: Collection,
     *     rankingRS: Collection,
     *     rankingRP: Collection,
     *     rankingFMF: Collection,
     *     finalRanking: Collection,
     *     rekomendasi: Collection
     * }
     */
    public function handle(): array
    {
        $rankingKriteria = $this->rankingKriteria();
        $alternatifLowongan = $this->alternatifLowongan();
        $numericTable = $this->numericTable();

        return [
            'mahasiswa' => $this->mahasiswa,
            'rankingKriteria' => $rankingKriteria,
            'alternatifLowongan' => $alternatifLowongan,
            'numericTable' => $numericTable,
            'normalisasiEuclidean' => $this->normalisasiEuclidean($numericTable),
            'normalisasiVektor' => $this->normalisasiVektor(),
            'rankingRS' => $this->rankingRS(),
            'rankingRP' => $this->rankingRP(),
            'rankingFMF' => $this->rankingFMF(),
            'finalRanking' => $this->rankingGlobal(),
            'rekomendasi' => $this->topRekomendasi(),
        ];
    }

    /**
     * @return array<int, array{nama: string, ranking: mixed, bobot: mixed}>
     */
    private function rankingKriteria(): array
    {
        if (! $this->mahasiswa) {
            return [];
        }

        return [
            [
                'nama' => 'Lokasi',
                'ranking' => optional($this->mahasiswa->kriteriaLokasiMagang)->rank,
                'bobot' => optional($this->mahasiswa->kriteriaLokasiMagang)->bobot,
            ],
            [
                'nama' => 'Pekerjaan',
                'ranking' => optional($this->mahasiswa->kriteriaPekerjaan)->rank,
                'bobot' => optional($this->mahasiswa->kriteriaPekerjaan)->bobot,
            ],
            [
                'nama' => 'Bidang Industri',
                'ranking' => optional($this->mahasiswa->kriteriaBidangIndustri)->rank,
                'bobot' => optional($this->mahasiswa->kriteriaBidangIndustri)->bobot,
            ],
            [
                'nama' => 'Open Remote',
                'ranking' => optional($this->mahasiswa->kriteriaOpenRemote)->rank,
                'bobot' => optional($this->mahasiswa->kriteriaOpenRemote)->bobot,
            ],
            [
                'nama' => 'Jenis Magang',
                'ranking' => optional($this->mahasiswa->kriteriaJenisMagang)->rank,
                'bobot' => optional($this->mahasiswa->kriteriaJenisMagang)->bobot,
            ],
        ];
    }

    private function alternatifLowongan(): Collection
    {
        $mahasiswaId = $this->mahasiswa?->id;

        $lowonganIds = $mahasiswaId
            ? FinalRankRecommendation::where('mahasiswa_id', $mahasiswaId)->pluck('lowongan_magang_id')->unique()
            : collect();

        return LowonganMagang::with(['lokasiMagang', 'perusahaan.bidangIndustri', 'pekerjaan'])
            ->when($lowonganIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $lowonganIds))
            ->limit(500)
            ->get();
    }

    private function numericTable(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswaId = $this->mahasiswa?->id;

        return EncodedAlternatives::with(['mahasiswa', 'lowonganMagang'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->limit(500)
            ->get();
    }

    private function normalisasiVektor(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswaId = $this->mahasiswa?->id;

        return VectorNormalization::with(['mahasiswa', 'lowonganMagang'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->limit(500)
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    private function normalisasiEuclidean(Collection $numericTable): array
    {
        if ($numericTable->isEmpty()) {
            return [
                'lokasi_magang' => 0,
                'open_remote' => 0,
                'jenis_magang' => 0,
                'bidang_industri' => 0,
                'pekerjaan' => 0,
            ];
        }

        // Daftar kolom kriteria numerik yang akan dihitung
        $criteriaColumns = ['pekerjaan', 'bidang_industri', 'lokasi_magang', 'open_remote', 'jenis_magang'];

        $euclideanNormalizationList = [];

        // Hitung Euclidean norm untuk masing-masing kolom
        foreach ($criteriaColumns as $column) {
            // Ambil semua nilai untuk kolom ini dan filter yang valid
            $values = $numericTable
                ->pluck($column)
                ->filter(function ($value) {
                    return ! is_null($value) && is_numeric($value);
                })
                ->map(function ($value) {
                    return (float) $value;
                });

            // Hitung sum of squares
            $sumOfSquares = $values->sum(function ($value) {
                return pow($value, 2);
            });

            // Hitung euclidean norm
            $euclideanNorm = $sumOfSquares > 0 ? sqrt($sumOfSquares) : 0;
            $euclideanNormalizationList[$column] = $euclideanNorm;
        }

        return $euclideanNormalizationList;
    }

    private function rankingRS(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswaId = $this->mahasiswa?->id;

        return RatioSystem::with(['mahasiswa', 'lowonganMagang'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->orderBy('rank')
            ->limit(500)
            ->get();
    }

    private function rankingRP(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswaId = $this->mahasiswa?->id;

        return ReferencePoint::with(['mahasiswa', 'lowonganMagang'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->orderBy('rank')
            ->limit(500)
            ->get();
    }

    private function rankingFMF(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswaId = $this->mahasiswa?->id;

        return FullMultiplicativeForm::with(['mahasiswa', 'lowonganMagang'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->orderBy('rank')
            ->limit(500)
            ->get();
    }

    private function rankingGlobal(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswaId = $this->mahasiswa?->id;

        return FinalRankRecommendation::with(['mahasiswa', 'lowonganMagang.perusahaan', 'ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
            ->where('mahasiswa_id', $mahasiswaId)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->orderBy('rank')
            ->limit(500)
            ->get();
    }

    private function topRekomendasi(): Collection
    {
        $tanggal = $this->tanggal;
        $mahasiswa = $this->mahasiswa;

        return FinalRankRecommendation::with(['mahasiswa', 'lowonganMagang', 'ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($tanggal, fn ($q) => $q->whereDate('created_at', $tanggal))
            ->when(! $tanggal, fn ($q) => $q->whereDate('created_at', now()->toDateString()))
            ->latestPerLowongan()
            ->orderBy('avg_rank')
            ->limit(10)
            ->get()
            ->values()
            ->map(function ($item, $index) {
                $item->display_rank = $index + 1;

                return $item;
            });
    }
}

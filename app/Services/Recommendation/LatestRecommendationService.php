<?php

namespace App\Services\Recommendation;

use App\Models\LowonganMagang;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extracted from resources/views/pages/mahasiswa/dashboard.blade.php.
 *
 * Owns the raw recommendation queries so the Volt component stays presentation
 * only: the "latest final_rank per lowongan" subquery, its eager-loaded lookup,
 * and the preference-label plucks. Behavior is byte-for-byte identical to the
 * inline version it replaced (same rows, same ascending-rank order, same
 * take(10), same null-filter, same resolved-label arrays).
 *
 * Stateless: no constructor dependencies, safe to resolve or `new` anywhere.
 */
class LatestRecommendationService
{
    /**
     * Latest final_rank recommendation per lowongan for a mahasiswa, with the
     * related pekerjaan / perusahaan / lokasi resolved into view-ready rows.
     *
     * @return array<int, array{
     *     rank: mixed,
     *     lowongan_id: mixed,
     *     pekerjaan: string,
     *     bidang_industri: string,
     *     lokasi: string,
     *     jenis_magang: mixed,
     *     open_remote: mixed,
     *     nama_perusahaan: string
     * }>
     */
    public function latestForMahasiswa(int|string $userId): array
    {
        // Latest final_rank per lowongan for this mahasiswa (parameterized).
        $latestRecommendationsSubquery = DB::table('final_rank_recommendation')
            ->select('lowongan_magang_id', DB::raw('MAX(created_at) as latest_created_at'))
            ->where('mahasiswa_id', $userId)
            ->groupBy('lowongan_magang_id');

        $uniqueRecommendations = DB::table('final_rank_recommendation as frr1')
            ->joinSub($latestRecommendationsSubquery, 'latest', function ($join) {
                $join->on('frr1.lowongan_magang_id', '=', 'latest.lowongan_magang_id')
                    ->on('frr1.created_at', '=', 'latest.latest_created_at');
            })
            ->where('frr1.mahasiswa_id', $userId)
            ->select('frr1.*')
            ->orderBy('frr1.rank', 'asc')
            ->take(10)
            ->get();

        // Single eager-loaded lookup to avoid N+1.
        $lowonganById = LowonganMagang::with(['perusahaan.bidangIndustri', 'pekerjaan', 'lokasiMagang'])
            ->whereIn('id', $uniqueRecommendations->pluck('lowongan_magang_id'))
            ->get()
            ->keyBy('id');

        return $uniqueRecommendations
            ->map(function ($item) use ($lowonganById) {
                /** @var \App\Models\LowonganMagang|null $lowonganMagang */
                $lowonganMagang = $lowonganById->get($item->lowongan_magang_id);

                if (! $lowonganMagang) {
                    return null;
                }

                $perusahaan = $lowonganMagang->perusahaan;

                return [
                    'rank' => $item->rank,
                    'lowongan_id' => $item->lowongan_magang_id,
                    'pekerjaan' => $lowonganMagang->pekerjaan->nama ?? '',
                    'bidang_industri' => $perusahaan->bidangIndustri->nama ?? '',
                    'lokasi' => $lowonganMagang->lokasiMagang->kategori_lokasi ?? 'Tidak Diketahui',
                    'jenis_magang' => $lowonganMagang->jenis_magang ?? '',
                    'open_remote' => $lowonganMagang->open_remote ?? '',
                    'nama_perusahaan' => $perusahaan->nama ?? '',
                ];
            })
            ->filter()
            ->toArray();
    }

    /**
     * Resolve the display label for each preference id in three lookup queries
     * (pekerjaan name, bidang industri name, lokasi kategori). Only queried when
     * the corresponding id is present; otherwise an empty collection is used,
     * exactly matching the inline behavior.
     *
     * @param  array<int, mixed>  $pekerjaanIds
     * @param  array<int, mixed>  $bidangIds
     * @param  array<int, mixed>  $lokasiIds
     * @return array{pekerjaan: Collection, bidang: Collection, lokasi: Collection}
     */
    public function preferenceLabels(array $pekerjaanIds, array $bidangIds, array $lokasiIds): array
    {
        $pekerjaanNama = $pekerjaanIds
            ? DB::table('pekerjaan')->whereIn('id', $pekerjaanIds)->pluck('nama', 'id')
            : collect();
        $bidangNama = $bidangIds
            ? DB::table('bidang_industri')->whereIn('id', $bidangIds)->pluck('nama', 'id')
            : collect();
        $lokasiKategori = $lokasiIds
            ? DB::table('lokasi_magang')->whereIn('id', $lokasiIds)->pluck('kategori_lokasi', 'id')
            : collect();

        return [
            'pekerjaan' => $pekerjaanNama,
            'bidang' => $bidangNama,
            'lokasi' => $lokasiKategori,
        ];
    }
}

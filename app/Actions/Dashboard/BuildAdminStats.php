<?php

namespace App\Actions\Dashboard;

use App\Actions\Action;
use App\Models\BidangIndustri;
use App\Models\FormPengajuanMagang;
use App\Models\KontrakMagang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Build the admin dashboard statistics array.
 *
 * Extracted verbatim from resources/views/pages/admin/dashboard.blade.php:8-29:
 * the inline grouped selectRaw status counts, the year-scoped kontrak count and
 * the top-5 bidang industri with their mahasiswa counts. Returns the exact same
 * array shape the Volt view previously built inline and consumes via `state()`.
 */
class BuildAdminStats implements Action
{
    /**
     * @return array{
     *     totalPengajuanMasuk: int,
     *     totalPengajuanDiterima: int,
     *     totalPengajuanDitolak: int,
     *     totalKontrakMagangTahunIni: int,
     *     bidangIndustriTerpopuler: array<int, array<string, mixed>>
     * }
     */
    public function handle(): array
    {
        $statusCounts = FormPengajuanMagang::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'totalPengajuanMasuk' => (int) ($statusCounts['diproses'] ?? 0),
            'totalPengajuanDiterima' => (int) ($statusCounts['diterima'] ?? 0),
            'totalPengajuanDitolak' => (int) ($statusCounts['ditolak'] ?? 0),

            'totalKontrakMagangTahunIni' => KontrakMagang::whereYear('created_at', Carbon::now()->year)->count(),

            'bidangIndustriTerpopuler' => BidangIndustri::withCount([
                'perusahaan as total_mahasiswa' => function ($query) {
                    $query->join('lowongan_magang', 'perusahaan.id', '=', 'lowongan_magang.perusahaan_id')->join('kontrak_magang', 'lowongan_magang.id', '=', 'kontrak_magang.lowongan_magang_id');
                },
            ])
                ->orderByDesc('total_mahasiswa')
                ->take(5)
                ->get()
                ->toArray(),
        ];
    }
}

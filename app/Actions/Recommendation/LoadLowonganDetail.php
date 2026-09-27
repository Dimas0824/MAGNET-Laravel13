<?php

namespace App\Actions\Recommendation;

use App\Actions\Action;
use App\Models\LowonganMagang;

/**
 * Load a single internship opening with the relations the student detail page
 * renders, plus the similar (same-pekerjaan, still open) openings shown in the
 * sidebar.
 *
 * Extracted verbatim from resources/views/pages/mahasiswa/detail-lowongan-magang.blade.php:
 * the `mount()` eager-load + findOrFail, and the `$lowonganSerupa` computed
 * (same `where('id', '!=', ...)`, `where('pekerjaan_id', ...)`,
 * `where('status', 'buka')`, `limit(4)` chain). Both the found opening and the
 * similar list are eager-loaded so the caller never triggers an N+1.
 */
class LoadLowonganDetail implements Action
{
    /**
     * @param  int  $id  lowongan_magang.id being viewed
     * @return array{lowongan: ?LowonganMagang, lowonganSerupa: \Illuminate\Support\Collection<int, LowonganMagang>}
     */
    public function handle(int $id = 0): array
    {
        try {
            $lowongan = LowonganMagang::with(['perusahaan.bidangIndustri', 'pekerjaan', 'lokasiMagang'])
                ->findOrFail($id);
        } catch (\Exception $e) {
            return [
                'lowongan' => null,
                'lowonganSerupa' => collect(),
            ];
        }

        return [
            'lowongan' => $lowongan,
            'lowonganSerupa' => $this->similarOpenings($lowongan),
        ];
    }

    /**
     * The similar openings: same pekerjaan, still open, excluding the current
     * one, capped at 4 — with the relations the sidebar card renders.
     *
     * @return \Illuminate\Support\Collection<int, LowonganMagang>
     */
    protected function similarOpenings(LowonganMagang $current): \Illuminate\Support\Collection
    {
        try {
            return LowonganMagang::with(['perusahaan', 'pekerjaan'])
                ->where('id', '!=', $current->id)
                ->where('pekerjaan_id', $current->pekerjaan_id)
                ->where('status', 'buka')
                ->limit(4)
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }
}

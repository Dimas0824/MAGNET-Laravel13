<?php

namespace App\Services;

use App\Models\BidangIndustri;
use App\Models\LokasiMagang;
use App\Models\Pekerjaan;

/**
 * Centralizes the lookup-table option maps used across views
 * (pekerjaan names, bidang industri names, lokasi magang kategori + labels).
 *
 * Stateless: no constructor dependencies, safe to resolve or `new` anywhere.
 */
class LookupService
{
    /**
     * @return array<int, string> id => nama
     */
    public function pekerjaanOptions(): array
    {
        return Pekerjaan::pluck('nama', 'id')->toArray();
    }

    /**
     * @return array<int, string> id => nama
     */
    public function bidangIndustriOptions(): array
    {
        return BidangIndustri::pluck('nama', 'id')->toArray();
    }

    /**
     * @return array<int, string> id => kategori_lokasi
     */
    public function lokasiMagangOptions(): array
    {
        return LokasiMagang::pluck('kategori_lokasi', 'id')->toArray();
    }

    /**
     * @return array<int, string> id => "kategori — lokasi"
     */
    public function lokasiMagangLabelOptions(): array
    {
        return LokasiMagang::get(['id', 'kategori_lokasi', 'lokasi'])
            ->mapWithKeys(fn ($l) => [$l->id => $l->kategori_lokasi.' — '.$l->lokasi])
            ->toArray();
    }
}

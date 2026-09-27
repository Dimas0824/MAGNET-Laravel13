<?php

namespace App\Traits;

use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait HasMultiMOORAProcess
{
    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function lowonganMagang()
    {
        return $this->belongsTo(LowonganMagang::class);
    }

    /**
     * Newest row per (mahasiswa, lowongan_magang_id), resolved in SQL so callers
     * do not load a whole stage table and de-duplicate in PHP.
     */
    public function scopeLatestPerLowongan(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->whereIn($table.'.id', function ($sub) use ($table) {
            $sub->selectRaw('MAX(stage.id)')
                ->from($table.' as stage')
                ->joinSub(
                    DB::table($table)
                        ->select('mahasiswa_id', 'lowongan_magang_id', DB::raw('MAX(created_at) as latest_created_at'))
                        ->groupBy('mahasiswa_id', 'lowongan_magang_id'),
                    'latest',
                    fn ($join) => $join->on('stage.lowongan_magang_id', '=', 'latest.lowongan_magang_id')
                        ->on('stage.created_at', '=', 'latest.latest_created_at')
                        ->on('stage.mahasiswa_id', '=', 'latest.mahasiswa_id')
                )
                ->groupBy('stage.mahasiswa_id', 'stage.lowongan_magang_id');
        });
    }
}

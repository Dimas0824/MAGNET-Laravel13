<?php

namespace App\Models;

use App\Traits\HasMultiMOORAProcess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FinalRankRecommendation extends Model
{
    use HasFactory, HasMultiMOORAProcess;

    protected $table = 'final_rank_recommendation';

    protected $fillable = [
        'mahasiswa_id',
        'lowongan_magang_id',
        'ratio_system_id',
        'reference_point_id',
        'fmf_id',
    ];

    /**
     * Newest row per (mahasiswa, lowongan_magang_id), resolved in SQL so callers
     * no longer load the whole table and de-duplicate in PHP.
     */
    public function scopeLatestPerLowongan(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->whereIn($table.'.id', function ($sub) use ($table) {
            $sub->selectRaw('MAX(frr.id)')
                ->from($table.' as frr')
                ->joinSub(
                    DB::table($table)
                        ->select('mahasiswa_id', 'lowongan_magang_id', DB::raw('MAX(created_at) as latest_created_at'))
                        ->groupBy('mahasiswa_id', 'lowongan_magang_id'),
                    'latest',
                    fn ($join) => $join->on('frr.lowongan_magang_id', '=', 'latest.lowongan_magang_id')
                        ->on('frr.created_at', '=', 'latest.latest_created_at')
                        ->on('frr.mahasiswa_id', '=', 'latest.mahasiswa_id')
                )
                ->groupBy('frr.mahasiswa_id', 'frr.lowongan_magang_id');
        });
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function lowonganMagang()
    {
        return $this->belongsTo(LowonganMagang::class, 'lowongan_magang_id');
    }

    public function ratioSystem()
    {
        return $this->belongsTo(RatioSystem::class, 'ratio_system_id');
    }

    public function referencePoint()
    {
        return $this->belongsTo(ReferencePoint::class, 'reference_point_id');
    }

    public function fullMultiplicativeForm()
    {
        return $this->belongsTo(FullMultiplicativeForm::class, 'fmf_id');
    }
}

<?php

namespace App\Models;

use App\Traits\HasMultiMOORAProcess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected $casts = [
        'avg_rank' => 'decimal:6',
    ];

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

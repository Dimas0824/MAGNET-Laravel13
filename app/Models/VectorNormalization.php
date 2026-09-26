<?php

namespace App\Models;

use App\Traits\HasMultiMOORAProcess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VectorNormalization extends Model
{
    use HasFactory, HasMultiMOORAProcess;

    protected $table = 'vector_normalization';

    protected $fillable = [
        'final_rank_recommendation_id',
        'pekerjaan',
        'open_remote',
        'jenis_magang',
        'bidang_industri',
        'lokasi_magang',
    ];

    protected $casts = [
        'pekerjaan' => 'decimal:6',
        'open_remote' => 'decimal:6',
        'jenis_magang' => 'decimal:6',
        'bidang_industri' => 'decimal:6',
        'lokasi_magang' => 'decimal:6',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function lowonganMagang()
    {
        return $this->belongsTo(LowonganMagang::class, 'lowongan_magang_id');
    }
}

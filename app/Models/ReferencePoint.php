<?php

namespace App\Models;

use App\Traits\HasMultiMOORAProcess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferencePoint extends Model
{
    use HasFactory, HasMultiMOORAProcess;

    protected $table = 'reference_point';

    protected $fillable = [
        'mahasiswa_id',
        'lowongan_magang_id',
        'pekerjaan',
        'open_remote',
        'jenis_magang',
        'bidang_industri',
        'lokasi_magang',
        'max_score',
    ];

    protected $casts = [
        'pekerjaan' => 'decimal:6',
        'open_remote' => 'decimal:6',
        'jenis_magang' => 'decimal:6',
        'bidang_industri' => 'decimal:6',
        'lokasi_magang' => 'decimal:6',
        'max_score' => 'decimal:6',
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

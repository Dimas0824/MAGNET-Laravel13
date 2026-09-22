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

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function lowonganMagang()
    {
        return $this->belongsTo(LowonganMagang::class, 'lowongan_magang_id');
    }
}

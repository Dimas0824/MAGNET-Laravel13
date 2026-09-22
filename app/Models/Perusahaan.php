<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    use HasFactory;

    protected $table = 'perusahaan';

    protected $fillable = [
        'nama',
        'bidang_industri_id',
        'lokasi',
        'kategori',
        'rating',
        'logo',
        'website',
        'deskripsi',
    ];

    protected $casts = [
        'kategori' => 'string',
        'rating' => 'float',
    ];

    public function lowonganMagang()
    {
        return $this->hasMany(LowonganMagang::class);
    }

    public function bidangIndustri()
    {
        return $this->belongsTo(BidangIndustri::class);
    }
}

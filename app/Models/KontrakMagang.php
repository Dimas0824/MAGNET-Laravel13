<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KontrakMagang extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'kontrak_magang';

    protected $fillable = [
        'mahasiswa_id',
        'dosen_id',
        'lowongan_magang_id',
        'waktu_awal',
        'waktu_akhir',
    ];

    protected $casts = [
        'waktu_awal' => 'datetime',
        'waktu_akhir' => 'datetime',
    ];

    /**
     * Relationship dengan Mahasiswa
     */
    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    /**
     * Relationship dengan Dosen Pembimbing
     */
    public function dosenPembimbing()
    {
        return $this->belongsTo(DosenPembimbing::class, 'dosen_id');
    }

    /**
     * Relationship dengan Lowongan Magang
     */
    public function lowonganMagang()
    {
        return $this->belongsTo(LowonganMagang::class, 'lowongan_magang_id');
    }

    /**
     * Relationship dengan Log Magang
     */
    public function logMagang()
    {
        return $this->hasMany(LogMagang::class, 'kontrak_magang_id');
    }

    /**
     * Relationship dengan Umpan Balik Magang
     */
    public function umpanBalikMagang()
    {
        return $this->hasMany(UmpanBalikMagang::class, 'kontrak_magang_id');
    }

    /**
     * Relationship dengan Ulasan Magang
     */
    public function ulasanMagang()
    {
        return $this->hasOne(UlasanMagang::class, 'kontrak_magang_id');
    }

    /**
     * Relationship dengan Chat
     */
    public function chats()
    {
        return $this->hasMany(Chat::class, 'kontrak_magang_id');
    }
}

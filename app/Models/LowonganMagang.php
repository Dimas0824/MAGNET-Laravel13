<?php

namespace App\Models;

use App\Events\LowonganMagangCreatedOrUpdated;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowonganMagang extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'lowongan_magang';

    protected $fillable = [
        'kuota',
        'pekerjaan_id',
        'deskripsi',
        'persyaratan',
        'jenis_magang',
        'open_remote',
        'lokasi_magang_id',
        'perusahaan_id',
    ];

    protected static function booted(): void
    {
        $categorizeDataToPrepareAlternatives = function (LowonganMagang $lowonganMagang) {
            event(new LowonganMagangCreatedOrUpdated($lowonganMagang));
        };

        static::created(function (LowonganMagang $lowonganMagang) use ($categorizeDataToPrepareAlternatives) {
            $categorizeDataToPrepareAlternatives($lowonganMagang);
        });

        static::updated(function (LowonganMagang $lowonganMagang) use ($categorizeDataToPrepareAlternatives) {
            $categorizeDataToPrepareAlternatives($lowonganMagang);
        });
    }

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class);
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class);
    }

    public function kontrak_magang()
    {
        return $this->hasMany(KontrakMagang::class);
    }
}

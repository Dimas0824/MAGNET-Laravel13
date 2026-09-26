<?php

namespace App\Models;

class KriteriaLokasiMagang extends BaseKriteriaModel
{
    protected const CRITERIA_KEY = MahasiswaKriteria::KEY_LOKASI_MAGANG;

    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
        'lokasi_magang_id',
    ];

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class);
    }
}

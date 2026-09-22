<?php

namespace App\Models;

class KriteriaLokasiMagang extends BaseKriteriaModel
{
    protected $table = 'kriteria_lokasi_magang';

    protected $fillable = [
        'lokasi_magang_id',
        'mahasiswa_id',
    ];

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class);
    }
}

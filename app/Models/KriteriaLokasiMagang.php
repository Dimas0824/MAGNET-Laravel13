<?php

namespace App\Models;

use App\Models\BaseKriteriaModel;
use App\Models\LokasiMagang;

class KriteriaLokasiMagang extends BaseKriteriaModel
{
    protected $table = 'kriteria_lokasi_magang';

    protected $fillable = [
        'lokasi_magang_id',
        'mahasiswa_id',
        'rank',
        'bobot'
    ];

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class);
    }
}

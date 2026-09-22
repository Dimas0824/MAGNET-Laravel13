<?php

namespace App\Models;

class KriteriaJenisMagang extends BaseKriteriaModel
{
    protected $table = 'kriteria_jenis_magang';

    protected $fillable = [
        'jenis_magang',
        'mahasiswa_id',
    ];
}

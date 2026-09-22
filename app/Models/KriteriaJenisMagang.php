<?php

namespace App\Models;

use App\Models\BaseKriteriaModel;

class KriteriaJenisMagang extends BaseKriteriaModel
{
    protected $table = 'kriteria_jenis_magang';

    protected $fillable = [
        'jenis_magang',
        'mahasiswa_id',
    ];
}

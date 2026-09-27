<?php

namespace App\Models;

class KriteriaJenisMagang extends BaseKriteriaModel
{
    protected const CRITERIA_KEY = MahasiswaKriteria::KEY_JENIS_MAGANG;

    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
        'jenis_magang',
    ];
}

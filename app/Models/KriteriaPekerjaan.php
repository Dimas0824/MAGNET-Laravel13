<?php

namespace App\Models;

class KriteriaPekerjaan extends BaseKriteriaModel
{
    protected const CRITERIA_KEY = MahasiswaKriteria::KEY_PEKERJAAN;

    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
        'pekerjaan_id',
    ];

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }
}

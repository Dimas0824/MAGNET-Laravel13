<?php

namespace App\Models;

class KriteriaPekerjaan extends BaseKriteriaModel
{
    protected $table = 'kriteria_pekerjaan';

    protected $fillable = [
        'pekerjaan_id',
        'mahasiswa_id',
    ];

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }
}

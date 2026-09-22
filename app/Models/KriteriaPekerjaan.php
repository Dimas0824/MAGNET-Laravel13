<?php

namespace App\Models;

use App\Models\Pekerjaan;
use App\Models\BaseKriteriaModel;

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

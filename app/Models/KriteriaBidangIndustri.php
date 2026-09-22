<?php

namespace App\Models;

class KriteriaBidangIndustri extends BaseKriteriaModel
{
    protected $table = 'kriteria_bidang_industri';

    protected $fillable = [
        'bidang_industri_id',
        'mahasiswa_id',
    ];

    public function bidangIndustri()
    {
        return $this->belongsTo(BidangIndustri::class);
    }
}

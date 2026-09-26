<?php

namespace App\Models;

class KriteriaBidangIndustri extends BaseKriteriaModel
{
    protected const CRITERIA_KEY = MahasiswaKriteria::KEY_BIDANG_INDUSTRI;

    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
        'bidang_industri_id',
    ];

    public function bidangIndustri()
    {
        return $this->belongsTo(BidangIndustri::class);
    }
}

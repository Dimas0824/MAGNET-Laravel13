<?php

namespace App\Models;

class KriteriaOpenRemote extends BaseKriteriaModel
{
    protected const CRITERIA_KEY = MahasiswaKriteria::KEY_OPEN_REMOTE;

    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
        'open_remote',
    ];
}

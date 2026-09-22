<?php

namespace App\Models;

class KriteriaOpenRemote extends BaseKriteriaModel
{
    protected $table = 'kriteria_open_remote';

    protected $fillable = [
        'open_remote',
        'mahasiswa_id',
    ];
}

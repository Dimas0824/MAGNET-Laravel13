<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormPengajuanMagang extends Model
{
    use HasFactory;

    protected $table = 'form_pengajuan_magang';

    protected $fillable = [
        'pengajuan_id',
        'keterangan',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function berkasPengajuanMagang()
    {
        return $this->belongsTo(BerkasPengajuanMagang::class, 'pengajuan_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BerkasPengajuanMagang extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'berkas_pengajuan_magang';

    protected $fillable = [
        'mahasiswa_id',
        'cv',
        'transkrip_nilai',
        'portfolio',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function formPengajuanMagang()
    {
        return $this->hasOne(FormPengajuanMagang::class, 'pengajuan_id');
    }
}

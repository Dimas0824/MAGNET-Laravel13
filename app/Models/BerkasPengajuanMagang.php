<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(AuditObserver::class)]
class BerkasPengajuanMagang extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

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

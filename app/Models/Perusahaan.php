<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(AuditObserver::class)]
class Perusahaan extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $table = 'perusahaan';

    protected $fillable = [
        'tenant_id',
        'nama',
        'bidang_industri_id',
        'lokasi_magang_id',
        'kategori',
        'logo',
        'website',
        'deskripsi',
    ];

    protected $casts = [
        'kategori' => 'string',
        'rating' => 'float',
    ];

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class, 'lokasi_magang_id');
    }

    public function lowonganMagang()
    {
        return $this->hasMany(LowonganMagang::class);
    }

    public function bidangIndustri()
    {
        return $this->belongsTo(BidangIndustri::class);
    }
}

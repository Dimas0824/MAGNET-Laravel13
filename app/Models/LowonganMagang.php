<?php

namespace App\Models;

use App\Events\LowonganMagangCreatedOrUpdated;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(AuditObserver::class)]
class LowonganMagang extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $table = 'lowongan_magang';

    protected $fillable = [
        'kuota',
        'pekerjaan_id',
        'deskripsi',
        'persyaratan',
        'jenis_magang',
        'open_remote',
        'lokasi_magang_id',
        'perusahaan_id',
    ];

    protected static function booted(): void
    {
        $categorizeDataToPrepareAlternatives = function (LowonganMagang $lowonganMagang) {
            event(new LowonganMagangCreatedOrUpdated($lowonganMagang));
        };

        static::created(function (LowonganMagang $lowonganMagang) use ($categorizeDataToPrepareAlternatives) {
            $categorizeDataToPrepareAlternatives($lowonganMagang);
        });

        static::updated(function (LowonganMagang $lowonganMagang) use ($categorizeDataToPrepareAlternatives) {
            $categorizeDataToPrepareAlternatives($lowonganMagang);
        });
    }

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class);
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class);
    }

    public function kontrak_magang()
    {
        return $this->hasMany(KontrakMagang::class);
    }

    /**
     * Only open openings. Mirrors the inline `->where('status', 'buka')` chain
     * used across the student views.
     */
    #[Scope]
    protected function buka(Builder $query): void
    {
        $query->where('status', 'buka');
    }

    /**
     * Restrict to a single company's openings. Mirrors the inline
     * `->where('perusahaan_id', $id)` chain in the student views.
     */
    #[Scope]
    protected function forPerusahaan(Builder $query, int $perusahaanId): void
    {
        $query->where('perusahaan_id', $perusahaanId);
    }
}

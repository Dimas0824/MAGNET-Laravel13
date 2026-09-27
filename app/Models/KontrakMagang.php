<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[ObservedBy(AuditObserver::class)]
class KontrakMagang extends Model
{
    use HasFactory, BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'kontrak_magang';

    protected $fillable = [
        'mahasiswa_id',
        'dosen_id',
        'lowongan_magang_id',
        'waktu_awal',
        'waktu_akhir',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'waktu_awal' => 'datetime',
        'waktu_akhir' => 'datetime',
    ];

    /**
     * Relationship dengan Mahasiswa
     */
    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    /**
     * Relationship dengan Dosen Pembimbing
     */
    public function dosenPembimbing()
    {
        return $this->belongsTo(DosenPembimbing::class, 'dosen_id');
    }

    /**
     * Relationship dengan Lowongan Magang
     */
    public function lowonganMagang()
    {
        return $this->belongsTo(LowonganMagang::class, 'lowongan_magang_id');
    }

    /**
     * Relationship dengan Log Magang
     */
    public function logMagang()
    {
        return $this->hasMany(LogMagang::class, 'kontrak_magang_id');
    }

    /**
     * Relationship dengan Umpan Balik Magang
     */
    public function umpanBalikMagang()
    {
        return $this->hasMany(UmpanBalikMagang::class, 'kontrak_magang_id');
    }

    /**
     * Relationship dengan Ulasan Magang
     */
    public function ulasanMagang()
    {
        return $this->hasOne(UlasanMagang::class, 'kontrak_magang_id');
    }

    /**
     * Relationship dengan Chat
     */
    public function chats()
    {
        return $this->hasMany(Chat::class, 'kontrak_magang_id');
    }

    /**
     * Limit to the kontrak belonging to a single mahasiswa.
     *
     * Mirrors the inline chain used across the mahasiswa views, e.g.
     * resources/views/pages/mahasiswa/log-mahasiswa.blade.php:34
     *   KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
     */
    #[Scope]
    protected function forMahasiswa(Builder $query, int $mahasiswaId): void
    {
        $query->where('mahasiswa_id', $mahasiswaId);
    }

    /**
     * Limit to kontrak that have non-empty feedback created within the last
     * $days days.
     *
     * Mirrors the inline exists() sub-query (DB::raw(1)) in
     * resources/views/pages/dosen/dashboard.blade.php:107-117.
     */
    #[Scope]
    protected function withFeedbackSince(Builder $query, int $days): void
    {
        $query->whereExists(function ($sub) use ($days) {
            $sub->select(DB::raw(1))
                ->from('umpan_balik_magang')
                ->whereRaw('umpan_balik_magang.kontrak_magang_id = kontrak_magang.id')
                ->where('umpan_balik_magang.created_at', '>=', now()->subDays($days))
                ->whereNotNull('umpan_balik_magang.komentar')
                ->where('umpan_balik_magang.komentar', '!=', '');
        });
    }
}

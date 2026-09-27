<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UmpanBalikMagang extends Model
{
    use HasFactory;

    protected $table = 'umpan_balik_magang';

    protected $fillable = [
        'kontrak_magang_id',
        'komentar',
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function kontrakMagang()
    {
        return $this->belongsTo(KontrakMagang::class);
    }

    /**
     * Limit to the feedback of a single kontrak magang.
     *
     * Mirrors the inline chain in
     * resources/views/pages/dosen/detail-mahasiswa-bimbingan.blade.php:86
     *   UmpanBalikMagang::where('kontrak_magang_id', $mahasiswa['kontrak_id'])
     */
    #[Scope]
    protected function forKontrak(Builder $query, int $kontrakMagangId): void
    {
        $query->where('kontrak_magang_id', $kontrakMagangId);
    }
}

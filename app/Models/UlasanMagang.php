<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(AuditObserver::class)]
class UlasanMagang extends Model
{
    use HasFactory, Auditable;

    protected $table = 'ulasan_magang';

    protected $fillable = [
        'kontrak_magang_id',
        'rating',
        'komentar',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function kontrakMagang()
    {
        return $this->belongsTo(KontrakMagang::class);
    }
}

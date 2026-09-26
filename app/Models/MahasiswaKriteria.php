<?php

namespace App\Models;

use App\Events\MahasiswaPreferenceUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The collapsed criteria row: one (mahasiswa, criteria_key) preference with a
 * typed nullable FK per criterion + a ROC weight.
 *
 * `bobot` is cast to `decimal:3` to match the (6,3) column (P4b). The run_key
 * parity gate is precision-independent (RecommendationRun::makeKey canonicalizes
 * every weight to 3 decimals before hashing), so this cast does not affect parity.
 */
class MahasiswaKriteria extends Model
{
    use HasFactory;

    public const KEY_PEKERJAAN = 'pekerjaan';
    public const KEY_BIDANG_INDUSTRI = 'bidang_industri';
    public const KEY_LOKASI_MAGANG = 'lokasi_magang';
    public const KEY_JENIS_MAGANG = 'jenis_magang';
    public const KEY_OPEN_REMOTE = 'open_remote';

    protected $table = 'mahasiswa_kriteria';

    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
        'pekerjaan_id',
        'bidang_industri_id',
        'lokasi_magang_id',
        'value_enum',
        'rank',
        'bobot',
    ];

    protected $casts = [
        'bobot' => 'decimal:3',
    ];

    /**
     * The collapsed table is now the source of truth for triggering a
     * recommendation recompute. The trigger moved here from BaseKriteriaModel
     * (which is now a read/write shim over this same table), so it fires
     * exactly once per preference change.
     */
    protected static function booted(): void
    {
        static::updated(function (MahasiswaKriteria $model) {
            $mahasiswa = Mahasiswa::find($model->mahasiswa_id);

            if (! $mahasiswa) {
                return;
            }

            // Debounce trigger antar pembaruan preferensi beruntun agar job tidak duplikat.
            $cacheKey = "recommendation:triggered:mahasiswa:{$mahasiswa->id}";
            if (! Cache::add($cacheKey, true, now()->addSeconds(10))) {
                return;
            }

            event(new MahasiswaPreferenceUpdated($mahasiswa));
        });
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function bidangIndustri()
    {
        return $this->belongsTo(BidangIndustri::class);
    }

    public function lokasiMagang()
    {
        return $this->belongsTo(LokasiMagang::class);
    }
}

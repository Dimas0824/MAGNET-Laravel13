<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * P3-T4 read-compat bridge.
 *
 * The 5 legacy `Kriteria*` models are the pipeline's public read/write surface
 * (`$mahasiswa->kriteriaPekerjaan`, `KriteriaPekerjaan::where('mahasiswa_id')`,
 * the preference wizard's `firstOrNew()->forceFill()->save()`). The collapsed
 * `mahasiswa_kriteria` table is now the single source of truth, so this base
 * points every subclass at that table and constrains each query to its own
 * `criteria_key`.
 *
 * The legacy tables (`kriteria_*`) still exist and are NOT read here — they are
 * dropped later in P3-T6.
 *
 * Shape compatibility:
 *  - `pekerjaan_id`, `bidang_industri_id`, `lokasi_magang_id` are real columns.
 *  - `jenis_magang` / `open_remote` are mapped to the collapsed `value_enum`.
 *  - `bobot` is NOT cast, so `(string) $model->bobot` stays the raw
 *    `decimal(30,15)` string the run_key parity gate hashes.
 */
abstract class BaseKriteriaModel extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa_kriteria';

    /**
     * The collapsed-table criteria_key this legacy model maps to.
     * Each concrete subclass must override it.
     */
    protected const CRITERIA_KEY = '';

    /**
     * Mirrors the legacy models' guarded surface: `rank` / `bobot` are
     * intentionally NOT mass-assignable (all writers use forceFill/forceCreate
     * — the preference wizard, the seeders, and the factories). Concrete
     * subclasses extend this with their own criterion-specific keys.
     */
    protected $fillable = [
        'mahasiswa_id',
        'criteria_key',
    ];

    /**
     * Default the criteria_key at instantiation time (NOT via a `creating`
     * model event): callers such as the preference wizard wrap writes in
     * `withoutEvents()`, which disables model events. A column default here is
     * always applied, so the NOT NULL collapsed column is never left unset.
     */
    public function __construct(array $attributes = [])
    {
        $attributes['criteria_key'] ??= static::CRITERIA_KEY ?: null;

        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        // Constrain every query to this model's criteria_key so the collapsed
        // table behaves like the per-criterion table the callers expect.
        static::addGlobalScope('criteria_key', function (Builder $builder) {
            if (static::CRITERIA_KEY !== '') {
                $builder->where($builder->getModel()->getTable().'.criteria_key', static::CRITERIA_KEY);
            }
        });
    }

    /**
     * Map the legacy `jenis_magang` / `open_remote` columns onto `value_enum`.
     */
    public function getAttribute($key)
    {
        if ($key === 'jenis_magang' || $key === 'open_remote') {
            return $this->attributes['value_enum'] ?? null;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'jenis_magang' || $key === 'open_remote') {
            $key = 'value_enum';
        }

        parent::setAttribute($key, $value);
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One MULTIMOORA computation for one mahasiswa.
 *
 * Existence of this row is the idempotency guarantee: the pipeline derives a
 * deterministic `run_key` from its inputs and does firstOrCreate(), so a
 * duplicate/retried run collapses onto the existing row instead of appending a
 * second snapshot of stage rows.
 *
 * It is also the history unit: every stage table row carries `run_id`, so
 * "recommendation history" is read as one group per run rather than by a
 * minute-truncated created_at (which collapsed runs landing in the same minute).
 */
class RecommendationRun extends Model
{
    use HasFactory, BelongsToTenant, MassPrunable;

    /** Retention window: stage/run rows are pruned after 365 days. */
    public const RETENTION_DAYS = 365;

    protected $table = 'recommendation_run';

    /**
     * Rows eligible for pruning: older than the retention window.
     */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope('tenant')
            ->where('created_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'mahasiswa_id',
        'run_key',
        'status',
        'opening_count',
    ];

    protected $casts = [
        'status' => 'string',
        'opening_count' => 'integer',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function encodedAlternatives()
    {
        return $this->hasMany(EncodedAlternatives::class, 'run_id');
    }

    public function vectorNormalizations()
    {
        return $this->hasMany(VectorNormalization::class, 'run_id');
    }

    public function ratioSystems()
    {
        return $this->hasMany(RatioSystem::class, 'run_id');
    }

    public function referencePoints()
    {
        return $this->hasMany(ReferencePoint::class, 'run_id');
    }

    public function fullMultiplicativeForms()
    {
        return $this->hasMany(FullMultiplicativeForm::class, 'run_id');
    }

    public function finalRanks()
    {
        return $this->hasMany(FinalRankRecommendation::class, 'run_id');
    }

    /**
     * Deterministic fingerprint of a pipeline's inputs.
     *
     * Derived only from data that determines the ranking (the encoded
     * alternative set for this mahasiswa plus the criteria weights), so the same
     * inputs always map to the same key while any material change produces a new
     * one. Sorted before hashing so a different row order is not a new run.
     *
     * @param  array<int, array<string, mixed>>  $encodedAlternatives
     * @param  array<string, float|int|string>  $weights
     */
    public static function makeKey(int $mahasiswaId, array $encodedAlternatives, array $weights): string
    {
        $alternatives = collect($encodedAlternatives)
            ->map(fn (array $row): array => [
                'lowongan_magang_id' => (int) ($row['lowongan_magang_id'] ?? 0),
                'pekerjaan' => (int) ($row['pekerjaan'] ?? 0),
                'open_remote' => (int) ($row['open_remote'] ?? 0),
                'jenis_magang' => (int) ($row['jenis_magang'] ?? 0),
                'bidang_industri' => (int) ($row['bidang_industri'] ?? 0),
                'lokasi_magang' => (int) ($row['lokasi_magang'] ?? 0),
            ])
            ->sortBy('lowongan_magang_id')
            ->values()
            ->all();

        ksort($weights);

        return hash('sha256', json_encode([
            'mahasiswa_id' => $mahasiswaId,
            'alternatives' => $alternatives,
            'weights' => $weights,
        ]));
    }
}

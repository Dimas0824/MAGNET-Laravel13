<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce recommendation_run as the parent of a single MULTIMOORA computation.
 *
 * Why: the recommendation pipeline was append-only with no run identity. Its
 * reads keyed "newest per (mahasiswa_id, lowongan_magang_id)" off created_at
 * truncated to the minute, so two runs inside one minute collapsed in the UI and
 * a MAX(created_at) join could fan out. There was also no way to make a retried
 * (ShouldQueue, tries=3) run idempotent: re-running appended a whole duplicate
 * set of stage rows.
 *
 * A run row fixes both: idempotency becomes `firstOrCreate(mahasiswa_id,
 * run_key)` (same inputs => same run => retry is a no-op), and history is read
 * by run_id instead of a minute-truncated timestamp.
 *
 * This migration is purely additive: it creates the parent table and adds a
 * NULLABLE run_id to each stage table, so existing rows and the current write
 * path keep working while the code is migrated in a later step (expand phase).
 */
return new class extends Migration
{
    /**
     * Stage tables produced by the pipeline, in write order.
     *
     * @var array<int, string>
     */
    private array $stageTables = [
        'encoded_alternatives',
        'vector_normalization',
        'ratio_system',
        'reference_point',
        'full_multiplicative_form',
        'final_rank_recommendation',
    ];

    public function up(): void
    {
        Schema::create('recommendation_run', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->onDelete('cascade');

            // Deterministic fingerprint of the inputs (encoded alternative set +
            // criteria weights + opening count). Same inputs => same key, which
            // is what makes a retried run collapse onto the existing row.
            $table->string('run_key', 64);

            $table->enum('status', ['running', 'done', 'failed'])->default('running');
            $table->unsignedInteger('opening_count')->default(0);

            $table->timestamps();

            // The idempotency guarantee lives here: one run per (mahasiswa, key).
            $table->unique(['mahasiswa_id', 'run_key'], 'recommendation_run_mahasiswa_key_unique');

            // Fast "latest run for this mahasiswa" lookups for the read path.
            $table->index(['mahasiswa_id', 'created_at'], 'recommendation_run_mahasiswa_created_index');
        });

        foreach ($this->stageTables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Nullable so existing rows (and the current writer) stay valid
                // until the code switch; nullOnDelete so clearing a run clears
                // its stage rows.
                //
                // NOTE: do NOT add a separate (run_id, lowongan_magang_id)
                // composite index here. Every stage table already has its own
                // FK index on lowongan_magang_id; MySQL will silently repurpose
                // a new composite index to back that FK and then refuse to drop
                // it (errno 1553), which makes the migration irreversible. The
                // FK below already gives run_id the index it needs.
                $blueprint->foreignId('run_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('recommendation_run')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->stageTables) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // dropConstrainedForeignId drops the FK (and its backing index)
                // in the correct order, so this is safely reversible now that no
                // separate composite index is created.
                $blueprint->dropConstrainedForeignId('run_id');
            });
        }

        Schema::dropIfExists('recommendation_run');
    }
};

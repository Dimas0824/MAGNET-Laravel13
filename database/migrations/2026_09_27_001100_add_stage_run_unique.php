<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4a-T1: UNIQUE(run_id, lowongan_magang_id) on the 6 stage tables.
 *
 * One row per (run, alternative) is a hard invariant of the pipeline: a re-run
 * must REPLACE a run's stage rows, never append duplicates. Without the pair
 * unique, a duplicate insert silently corrupts the ranking.
 *
 * DEDUP FIRST: a database that predates the invariant may already hold
 * duplicates (the point of this migration), so collapse them to the latest row
 * per pair before adding the index — otherwise the ALTER would fail.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $stageTables = [
        'encoded_alternatives',
        'ratio_system',
        'reference_point',
        'full_multiplicative_form',
        'vector_normalization',
        'final_rank_recommendation',
    ];

    public function up(): void
    {
        foreach ($this->stageTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! $this->hasPairColumns($table)) {
                continue;
            }

            $this->dedup($table);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique(['run_id', 'lowongan_magang_id'], "{$this->tableName($blueprint)}_run_id_lowongan_unique");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->stageTables) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // The unique (run_id, lowongan_magang_id) also backs the run_id FK,
            // so MySQL refuses to drop it while the FK needs an index. Give the
            // FK a plain run_id index first, then drop the unique.
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index('run_id', "{$table}_run_id_index");
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique("{$table}_run_id_lowongan_unique");
            });
        }
    }

    private function hasPairColumns(string $table): bool
    {
        return Schema::hasColumn($table, 'run_id')
            && Schema::hasColumn($table, 'lowongan_magang_id');
    }

    /**
     * Keep only the newest row per (run_id, lowongan_magang_id); delete the rest.
     */
    private function dedup(string $table): void
    {
        if (! Schema::hasColumn($table, 'id')) {
            return;
        }

        DB::statement(
            "DELETE t1 FROM `{$table}` t1
             JOIN `{$table}` t2
               ON t1.run_id = t2.run_id
              AND t1.lowongan_magang_id = t2.lowongan_magang_id
              AND t1.id < t2.id"
        );
    }

    private function tableName(Blueprint $blueprint): string
    {
        return $blueprint->getTable();
    }
};

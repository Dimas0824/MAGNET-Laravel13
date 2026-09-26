<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P6-T5 CONTRACT: make business-critical FKs ON DELETE RESTRICT.
 *
 * Deleting a student/company/contract must NOT silently cascade away the rows
 * that ARE the evidence of an internship (contract, documents, logs, reviews).
 * Stage rows under a run stay CASCADE (they are a recomputable snapshot).
 *
 * The FK must be dropped and re-created to change its delete rule.
 */
return new class extends Migration
{
    /**
     * table => [ [column, referencedTable], ... ]
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    private array $restrict = [
        'kontrak_magang' => [['mahasiswa_id', 'mahasiswa'], ['dosen_id', 'dosen_pembimbing'], ['lowongan_magang_id', 'lowongan_magang']],
        'berkas_pengajuan_magang' => [['mahasiswa_id', 'mahasiswa']],
        'lowongan_magang' => [['perusahaan_id', 'perusahaan']],
        'log_magang' => [['kontrak_magang_id', 'kontrak_magang']],
        'ulasan_magang' => [['kontrak_magang_id', 'kontrak_magang']],
        'umpan_balik_magang' => [['kontrak_magang_id', 'kontrak_magang']],
        'form_pengajuan_magang' => [['pengajuan_id', 'berkas_pengajuan_magang']],
    ];

    /**
     * Stage tables whose `run_id` FK must CASCADE: a deleted run takes its
     * recomputable snapshot rows with it (they are NOT independent history).
     *
     * @var array<int, string>
     */
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
        foreach ($this->restrict as $table => $fks) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($fks as [$column, $referenced]) {
                if (! Schema::hasColumn($table, $column) || ! Schema::hasTable($referenced)) {
                    continue;
                }

                $this->dropForeignIfExists($table, $column);

                Schema::table($table, function (Blueprint $blueprint) use ($column, $referenced) {
                    $blueprint->foreign($column)->references('id')->on($referenced)->restrictOnDelete();
                });
            }
        }

        // Stage rows under a run cascade with the run (recomputable snapshot).
        foreach ($this->stageTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'run_id')) {
                continue;
            }

            $this->dropForeignIfExists($table, 'run_id');

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('run_id')->references('id')->on('recommendation_run')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->restrict as $table => $fks) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($fks as [$column, $referenced]) {
                if (! Schema::hasColumn($table, $column) || ! Schema::hasTable($referenced)) {
                    continue;
                }

                $this->dropForeignIfExists($table, $column);

                Schema::table($table, function (Blueprint $blueprint) use ($column, $referenced) {
                    $blueprint->foreign($column)->references('id')->on($referenced)->cascadeOnDelete();
                });
            }
        }

        // Restore the prior SET NULL on stage run_id FKs.
        foreach ($this->stageTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'run_id')) {
                continue;
            }

            $this->dropForeignIfExists($table, 'run_id');

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('run_id')->references('id')->on('recommendation_run')->nullOnDelete();
            });
        }
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $constraint = DB::table('information_schema.key_column_usage')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint !== null) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};

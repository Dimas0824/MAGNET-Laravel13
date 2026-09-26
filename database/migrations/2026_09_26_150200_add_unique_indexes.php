<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the UNIQUE indexes that make the application's writes idempotent.
 *
 * On MySQL a plain INSERT can never be idempotent, and Eloquent's upsert()
 * ignores its uniqueBy argument — it relies entirely on the table's real
 * PRIMARY/UNIQUE indexes to detect conflicts. So these are the mechanism, not
 * an optimisation: without them, updateOrCreate/upsert still duplicates.
 *
 * Stage tables deliberately get NO bare (mahasiswa_id, lowongan_magang_id)
 * unique: they are now append-per-run (a run_id FK), so collapsing them would
 * destroy the history the reads depend on. Their idempotency comes from
 * recommendation_run.run_key plus a run-scoped replace in the writer.
 *
 * Runs AFTER 2026_09_26_150100_dedup_before_unique_indexes, which commits the
 * duplicate removal first.
 */
return new class extends Migration
{
    /**
     * table => [columns, explicit index name].
     *
     * Names are explicit because MySQL identifiers are capped at 64 characters
     * and the auto-generated table+columns+suffix form overflows for the
     * multi-column entries.
     *
     * @var array<int, array{0: string, 1: array<int, string>, 2: string}>
     */
    private array $uniques = [
        ['encoded_alternatives', ['mahasiswa_id', 'lowongan_magang_id'], 'enc_alt_mhs_lowongan_unique'],
        ['kriteria_pekerjaan', ['mahasiswa_id'], 'krit_pekerjaan_mhs_unique'],
        ['kriteria_bidang_industri', ['mahasiswa_id'], 'krit_bidang_mhs_unique'],
        ['kriteria_lokasi_magang', ['mahasiswa_id'], 'krit_lokasi_mhs_unique'],
        ['kriteria_jenis_magang', ['mahasiswa_id'], 'krit_jenis_mhs_unique'],
        ['kriteria_open_remote', ['mahasiswa_id'], 'krit_remote_mhs_unique'],
        ['berkas_pengajuan_magang', ['mahasiswa_id'], 'berkas_mhs_unique'],
        ['form_pengajuan_magang', ['pengajuan_id'], 'form_pengajuan_unique'],
        ['log_magang', ['kontrak_magang_id', 'tanggal'], 'log_kontrak_tanggal_unique'],
        ['ulasan_magang', ['kontrak_magang_id'], 'ulasan_kontrak_unique'],
        ['perusahaan', ['nama'], 'perusahaan_nama_unique'],
        ['pekerjaan', ['nama'], 'pekerjaan_nama_unique'],
        ['kontrak_magang', ['mahasiswa_id'], 'kontrak_mhs_unique'],
    ];

    public function up(): void
    {
        foreach ($this->uniques as [$table, $columns, $name]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->unique($columns, $name);
            });
        }
    }

    /**
     * Single-column FK columns these uniques cover. On MySQL, adding a unique
     * on a column that already backs a foreign key can leave MySQL managing a
     * single physical index under one of the two names, so a naive dropUnique()
     * can fail with 1553 (index needed by FK) or 1091 (index does not exist).
     *
     * @var array<string, array<int, string>>
     */
    private array $fkColumnFallbacks = [
        'kontrak_magang' => ['mahasiswa_id'],
        'berkas_pengajuan_magang' => ['mahasiswa_id'],
        'form_pengajuan_magang' => ['pengajuan_id'],
        'kriteria_pekerjaan' => ['mahasiswa_id'],
        'kriteria_bidang_industri' => ['mahasiswa_id'],
        'kriteria_lokasi_magang' => ['mahasiswa_id'],
        'kriteria_jenis_magang' => ['mahasiswa_id'],
        'kriteria_open_remote' => ['mahasiswa_id'],
        'ulasan_magang' => ['kontrak_magang_id'],
        // Composite unique whose LEFTMOST column backs a FK; MySQL uses the
        // prefix as the FK's index, so a plain index on that column is needed.
        'log_magang' => ['kontrak_magang_id'],
        'encoded_alternatives' => ['mahasiswa_id', 'lowongan_magang_id'],
    ];

    public function down(): void
    {
        foreach (array_reverse($this->uniques) as [$table, $columns, $name]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Resolve the live index name first: MySQL may have kept only the
            // FK's index (or only ours) on the same column.
            $liveName = $this->liveUniqueName($table, $columns, $name);
            if ($liveName === null) {
                continue;
            }

            // Make sure an FK column still has a plain index before the unique
            // is removed, otherwise the drop is refused (errno 1553).
            $this->ensureForeignKeyIndex($table);

            Schema::table($table, function (Blueprint $blueprint) use ($liveName) {
                $blueprint->dropUnique($liveName);
            });
        }
    }

    /**
     * Return the name of an existing single-column unique index on the table
     * that matches our intended columns, or null if none exists.
     *
     * @param  array<int, string>  $columns
     */
    private function liveUniqueName(string $table, array $columns, string $preferred): ?string
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Non_unique = 0');

        // Group index names by their column set.
        $byName = [];
        foreach ($rows as $row) {
            $byName[$row->Key_name][$row->Seq_in_index] = $row->Column_name;
        }

        foreach ($byName as $indexName => $cols) {
            ksort($cols);
            if (array_values($cols) === $columns) {
                return $indexName;
            }
        }

        return null;
    }

    /**
     * Guarantee each FK column of the table has a NON-UNIQUE index. A unique
     * index on the column does not count: MySQL will still refuse to drop it
     * while it is the FK's only backing index (errno 1553), so a plain index
     * must be added first.
     */
    private function ensureForeignKeyIndex(string $table): void
    {
        $indexes = collect(DB::select('SHOW INDEX FROM `'.$table.'`'));

        foreach ($this->fkColumnFallbacks[$table] ?? [] as $column) {
            $hasPlainIndex = $indexes->contains(
                fn ($row) => $row->Column_name === $column
                    && (int) $row->Seq_in_index === 1
                    && (int) $row->Non_unique === 1
            );

            if (! $hasPlainIndex) {
                $name = $table.'_'.$column.'_fk_index';
                // Name may still be taken by a leftover from an aborted run.
                if (! $indexes->contains(fn ($row) => $row->Key_name === $name)) {
                    DB::statement('ALTER TABLE `'.$table.'` ADD INDEX `'.$name.'` (`'.$column.'`)');
                }
            }
        }
    }
};

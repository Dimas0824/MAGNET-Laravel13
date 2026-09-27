<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De-duplicate tables before UNIQUE indexes are added.
 *
 * Adding a UNIQUE index to a table that already holds duplicate rows fails
 * (and on a populated production DB would be a hard stop mid-deploy). This runs
 * as its own migration so the DELETE is committed BEFORE the DDL in the paired
 * migration, and it is guarded: an empty table is a no-op.
 *
 * Keeps the row with the highest id per grouping key (the newest writer).
 */
return new class extends Migration
{
    /**
     * table => [grouping columns] to collapse on.
     *
     * @var array<string, array<int, string>>
     */
    private array $targets = [
        'encoded_alternatives' => ['mahasiswa_id', 'lowongan_magang_id'],
        'kriteria_pekerjaan' => ['mahasiswa_id'],
        'kriteria_bidang_industri' => ['mahasiswa_id'],
        'kriteria_lokasi_magang' => ['mahasiswa_id'],
        'kriteria_jenis_magang' => ['mahasiswa_id'],
        'kriteria_open_remote' => ['mahasiswa_id'],
        'berkas_pengajuan_magang' => ['mahasiswa_id'],
        'form_pengajuan_magang' => ['pengajuan_id'],
        'log_magang' => ['kontrak_magang_id', 'tanggal'],
        'ulasan_magang' => ['kontrak_magang_id'],
        'perusahaan' => ['nama'],
        'pekerjaan' => ['nama'],
        'kontrak_magang' => ['mahasiswa_id'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Guard: nothing to do on an empty table.
            if (DB::table($table)->count() === 0) {
                continue;
            }

            $join = collect($columns)
                ->map(fn (string $c): string => "t1.`{$c}` = t2.`{$c}`")
                ->implode(' AND ');

            // Delete every row that has a newer sibling with the same key.
            DB::statement("DELETE t1 FROM `{$table}` t1 JOIN `{$table}` t2 ON {$join} AND t1.id < t2.id");
        }
    }

    public function down(): void
    {
        // De-duplication is destructive by nature and intentionally not
        // reversible; the paired index migration's down() reverses the schema
        // change this exists to enable.
    }
};

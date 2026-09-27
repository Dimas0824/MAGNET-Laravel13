<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-T2: backfill `mahasiswa_kriteria` from the 5 `kriteria_*` tables.
 *
 * `bobot` is copied VERBATIM (raw column value, no PHP float round-trip) so the
 * exact decimal(30,15) string the run_key parity gate hashes is preserved.
 *
 * down() is a no-op: the table drop in P3-T1 down() reverses the schema.
 */
return new class extends Migration
{
    /** criteria_key => [source table, typed column, enum column] */
    private array $map = [
        'pekerjaan' => ['table' => 'kriteria_pekerjaan', 'fk' => 'pekerjaan_id', 'enum' => null],
        'bidang_industri' => ['table' => 'kriteria_bidang_industri', 'fk' => 'bidang_industri_id', 'enum' => null],
        'lokasi_magang' => ['table' => 'kriteria_lokasi_magang', 'fk' => 'lokasi_magang_id', 'enum' => null],
        'jenis_magang' => ['table' => 'kriteria_jenis_magang', 'fk' => null, 'enum' => 'jenis_magang'],
        'open_remote' => ['table' => 'kriteria_open_remote', 'fk' => null, 'enum' => 'open_remote'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('mahasiswa_kriteria')) {
            return;
        }

        foreach ($this->map as $key => $src) {
            if (! Schema::hasTable($src['table'])) {
                continue;
            }

            DB::table($src['table'])->orderBy('id')->chunkById(200, function ($rows) use ($key, $src) {
                foreach ($rows as $row) {
                    $exists = DB::table('mahasiswa_kriteria')
                        ->where('mahasiswa_id', $row->mahasiswa_id)
                        ->where('criteria_key', $key)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('mahasiswa_kriteria')->insert([
                        'mahasiswa_id' => $row->mahasiswa_id,
                        'criteria_key' => $key,
                        'pekerjaan_id' => $src['fk'] === 'pekerjaan_id' ? $row->pekerjaan_id : null,
                        'bidang_industri_id' => $src['fk'] === 'bidang_industri_id' ? $row->bidang_industri_id : null,
                        'lokasi_magang_id' => $src['fk'] === 'lokasi_magang_id' ? $row->lokasi_magang_id : null,
                        // Verbatim raw value (no PHP cast) preserves the exact
                        // decimal string the parity gate depends on.
                        'value_enum' => $src['enum'] !== null ? $row->{$src['enum']} : null,
                        'rank' => $row->rank,
                        'bobot' => $row->bobot,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: schema reversal is P3-T1's down().
    }
};

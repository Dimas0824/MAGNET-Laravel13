<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4a-T2: resize stage numeric columns decimal(30,15) -> decimal(9,6).
 *
 * (30,15) is absurdly wide: it bloats every stage row and index, and the
 * pipeline never needs 15 fractional digits. (9,6) keeps six fractional digits
 * — more than enough for normalized MULTIMOORA scores — and is metadata-only on
 * InnoDB for most rows.
 *
 * Criteria `bobot` is intentionally NOT touched here: its resize (->(6,3))
 * changes the run_key string and is gated behind GATE-PARITY (P4b).
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $resize = [
        'ratio_system' => ['score'],
        'full_multiplicative_form' => ['score'],
        'reference_point' => ['pekerjaan', 'open_remote', 'jenis_magang', 'bidang_industri', 'lokasi_magang', 'max_score'],
        'vector_normalization' => ['pekerjaan', 'open_remote', 'jenis_magang', 'bidang_industri', 'lokasi_magang'],
        'final_rank_recommendation' => ['avg_rank'],
    ];

    public function up(): void
    {
        foreach ($this->resize as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->changeDecimal($table, $column, 9, 6);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->resize) as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_reverse($columns) as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->changeDecimal($table, $column, 30, 15);
            }
        }
    }

    /**
     * Change a decimal column's precision/scale. Uses a raw MODIFY so the
     * nullable-ness and default are preserved exactly (change() reformats the
     * whole column definition and can drop attributes).
     */
    private function changeDecimal(string $table, string $column, int $precision, int $scale): void
    {
        $nullable = Schema::getColumnType($table, $column) !== null
            ? DB::table('information_schema.columns')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->value('is_nullable')
            : 'YES';

        $null = ($nullable === 'YES') ? 'NULL' : 'NOT NULL';

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DECIMAL({$precision},{$scale}) {$null}");
    }
};

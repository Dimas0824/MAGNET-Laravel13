<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4b-T1: resize `mahasiswa_kriteria.bobot` decimal(30,15) -> decimal(6,3).
 *
 * V9 requires the collapsed criteria weight to be a (6,3) decimal, never
 * (30,15). The design (26-migration-rollout Phase 4) resizes both the stage
 * decimals (->(9,6), done in P4a) and `bobot` (->(6,3), here).
 *
 * RUN_KEY SAFETY: this is the ONLY change that alters the physical string a
 * `bobot` read returns (`0.456666666666670` -> `0.457`). It is safe because
 * `RecommendationRun::makeKey()` canonicalizes every weight to a fixed
 * 3-decimal string (`WEIGHT_SCALE`) before hashing, so the derived key is
 * precision-independent and does NOT drift across this resize. See
 * `tests/Feature/Parity/RunKeyParityTest.php` and `Criteria/BobotResizeTest`.
 *
 * (6,3) holds the full ROC weight range: ROC weights sum to 1 and each is
 * <= 1 < 999.999, so no value can overflow the column.
 *
 * Metadata-only on InnoDB for the values in use; the fractional digits are
 * dropped (3 kept), which is exactly the intended storage narrowing.
 */
return new class extends Migration
{
    private const TABLE = 'mahasiswa_kriteria';

    private const COLUMN = 'bobot';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $this->changeDecimal(6, 3);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $this->changeDecimal(30, 15);
    }

    /**
     * Raw MODIFY so non-null/default attributes are preserved exactly
     * (`change()` reformats the whole column definition).
     */
    private function changeDecimal(int $precision, int $scale): void
    {
        $nullable = DB::table('information_schema.columns')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', self::TABLE)
            ->where('column_name', self::COLUMN)
            ->value('is_nullable');

        $null = ($nullable === 'YES') ? 'NULL' : 'NOT NULL';

        DB::statement('ALTER TABLE `'.self::TABLE.'` MODIFY `'.self::COLUMN.'` DECIMAL('.$precision.','.$scale.') '.$null);
    }
};
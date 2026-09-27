<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5-T1: add a nullable `lokasi_magang_id` FK to `perusahaan`.
 *
 * The legacy `perusahaan.lokasi` free text could not be matched reliably by the
 * pipeline; a FK to the `lokasi_magang` lookup makes location categorizable.
 * Nullable here so the backfill (P5-T2) can run before the NOT NULL/drop
 * contract (P5-T4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perusahaan') || Schema::hasColumn('perusahaan', 'lokasi_magang_id')) {
            return;
        }

        Schema::table('perusahaan', function (Blueprint $blueprint) {
            $blueprint->foreignId('lokasi_magang_id')
                ->nullable()
                ->after('kategori')
                ->constrained('lokasi_magang')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perusahaan') || ! Schema::hasColumn('perusahaan', 'lokasi_magang_id')) {
            return;
        }

        Schema::table('perusahaan', function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('lokasi_magang_id');
        });
    }
};

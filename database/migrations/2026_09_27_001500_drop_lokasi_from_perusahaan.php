<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5-T4 CONTRACT: drop the legacy `perusahaan.lokasi` free-text column.
 *
 * Safe only AFTER P5-T3 retired every reader (the dashboard, DataPreprocessing
 * and the admin/profile pages now use `lokasi_magang_id`). down() restores the
 * column so the schema stays reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perusahaan') || ! Schema::hasColumn('perusahaan', 'lokasi')) {
            return;
        }

        Schema::table('perusahaan', function (Blueprint $blueprint) {
            $blueprint->dropColumn('lokasi');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perusahaan') || Schema::hasColumn('perusahaan', 'lokasi')) {
            return;
        }

        Schema::table('perusahaan', function (Blueprint $blueprint) {
            $blueprint->string('lokasi', 100)->nullable()->after('bidang_industri_id');
        });
    }
};

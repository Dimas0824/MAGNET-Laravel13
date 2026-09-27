<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-T1: create the collapsed `mahasiswa_kriteria` table.
 *
 * Replaces the 5 `kriteria_*` tables: one row per (mahasiswa, criteria_key)
 * with a typed nullable FK per criterion. `bobot` stays `decimal(30,15)` here
 * so the backfill copies it VERBATIM — the run_key parity gate hashes the exact
 * DB-cast string, so the resize to (6,3) is deferred to P4b (post-parity).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mahasiswa_kriteria')) {
            return;
        }

        Schema::create('mahasiswa_kriteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->cascadeOnDelete();
            $table->enum('criteria_key', ['pekerjaan', 'bidang_industri', 'lokasi_magang', 'jenis_magang', 'open_remote']);
            $table->foreignId('pekerjaan_id')->nullable()->constrained('pekerjaan')->nullOnDelete();
            $table->foreignId('bidang_industri_id')->nullable()->constrained('bidang_industri')->nullOnDelete();
            $table->foreignId('lokasi_magang_id')->nullable()->constrained('lokasi_magang')->nullOnDelete();
            $table->string('value_enum', 30)->nullable();
            $table->unsignedTinyInteger('rank');
            $table->decimal('bobot', 30, 15);
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'criteria_key'], 'mahasiswa_kriteria_mhs_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_kriteria');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W0-T0.2: add the registration-audit columns to `kontrak_magang`.
 *
 *   - `tanggal_daftar`  : the BUSINESS registration date — when the student
 *                         registered for this contract. This is distinct from
 *                         `created_at`, which REMAINS the canonical
 *                         row-creation timestamp (audit of when the DB row
 *                         was written). Do not conflate the two.
 *   - `surat_izin_path` : storage path of the permit letter (surat izin)
 *                         uploaded for this contract.
 *
 * Why `surat_izin_path` lives on `kontrak_magang` and NOT on `lowongan_magang`:
 *   `kontrak_magang` is 1:1 with the participant, whereas `lowongan_magang`
 *   partner rows are SHARED across many applicants. The permit belongs to the
 *   individual 1:1 kontrak, so it must never be pushed onto the shared
 *   lowongan row (which would leak one applicant's permit into every sibling).
 *
 * Both columns are nullable: legacy rows predate registration and many
 * contracts have no permit uploaded yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kontrak_magang', function (Blueprint $table) {
            $table->date('tanggal_daftar')->nullable()->after('status');
            $table->string('surat_izin_path')->nullable()->after('tanggal_daftar');
        });
    }

    public function down(): void
    {
        Schema::table('kontrak_magang', function (Blueprint $table) {
            $table->dropColumn(['surat_izin_path', 'tanggal_daftar']);
        });
    }
};

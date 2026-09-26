<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nullable `tenant_id` (FK -> tenants) to the 12 ROOT tables only (ADR 03).
 *
 * Children (log_magang, chats, ulasan, umpan_balik, mahasiswa_kriteria, the
 * stage tables, form_pengajuan_magang) inherit tenancy via their parent join.
 * Nullable here; the NOT NULL contract is a separate later migration (P1-T7).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $rootTables = [
        'users',
        'mahasiswa',
        'dosen_pembimbing',
        'admin',
        'bidang_industri',
        'pekerjaan',
        'lokasi_magang',
        'perusahaan',
        'lowongan_magang',
        'berkas_pengajuan_magang',
        'kontrak_magang',
        'recommendation_run',
    ];

    public function up(): void
    {
        foreach ($this->rootTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('tenants')
                    ->nullOnDelete();

                $blueprint->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->rootTables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};

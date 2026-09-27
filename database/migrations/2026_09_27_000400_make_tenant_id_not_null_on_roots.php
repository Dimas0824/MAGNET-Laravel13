<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-T7 CONTRACT: make `tenant_id` NOT NULL on the 12 ROOT tables (ADR 03).
 *
 * Safe only AFTER the P1-T3 backfill proved 0 NULL rows; a NOT NULL column on
 * otherwise-NULL data would fail. Once applied, an un-attributed row is
 * impossible at the schema level.
 *
 * up() re-asserts the backfill defensively (idempotent) so the ALTER cannot
 * fail on a database that missed the data migration step.
 *
 * down() restores the nullable shape so the schema stays reversible.
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
        // Defensive, idempotent backfill: never ALTER a column with NULL values.
        $defaultTenantId = DB::table('tenants')
            ->where('slug', \Database\Seeders\TenantSeeder::DEFAULT_SLUG)
            ->value('id');

        foreach ($this->rootTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            if ($defaultTenantId !== null) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
            }

            // The column was added with ON DELETE SET NULL (compatible with a
            // nullable column). A NOT NULL column cannot be SET NULL, so the FK
            // must first be dropped and re-created (then RESTRICT on delete: a
            // tenant must not silently null out or cascade away its roots).
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['tenant_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
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
                $blueprint->dropForeign(['tenant_id']);
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('tenant_id')->nullable()->change();
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            });
        }
    }
};

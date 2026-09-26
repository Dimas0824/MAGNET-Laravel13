<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-T4: soft-delete `kontrak_magang` + `mahasiswa` via `deleted_at`.
 *
 * History must survive a "delete": a contract a student finished, or a student
 * record, is archived rather than destroyed. R5: any UNIQUE that includes a
 * soft-deletable row must tolerate the deleted duplicate, so the unique index
 * is rebuilt to include `deleted_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['kontrak_magang', 'mahasiswa'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['mahasiswa', 'kontrak_magang'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};

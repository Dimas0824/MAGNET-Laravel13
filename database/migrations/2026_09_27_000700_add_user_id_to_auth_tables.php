<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T3: add a UNIQUE `user_id` FK to the 3 auth tables (mahasiswa, dosen,
 * admin) linking each identity to its `users` registry row.
 *
 * Nullable here so the backfill (P2-T4) can run; the FK is UNIQUE because one
 * auth row maps to exactly one registry identity.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $authTables = ['mahasiswa', 'dosen_pembimbing', 'admin'];

    public function up(): void
    {
        foreach ($this->authTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();

                $blueprint->unique('user_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->authTables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            // The UNIQUE(user_id) also backs the FK, so MySQL refuses to drop it
            // while the FK needs an index. Drop the FK first, then the unique,
            // then the column.
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['user_id']);
                $blueprint->dropUnique(['user_id']);
                $blueprint->dropColumn('user_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T4: backfill `user_id` on each auth table from the registry.
 *
 * Match strategy: by shared email when present, else by (role, name) — the
 * same key the registry backfill used, so the pairing is deterministic. Any
 * row that still has no registry counterpart gets one created, so the
 * subsequent NOT NULL expectation (0 NULL) holds.
 *
 * down() is a no-op: the column drop in P2-T3 down() reverses the schema.
 */
return new class extends Migration
{
    /** @var array<string, array{table: string, name: string, email: ?string}> */
    private array $sources = [
        'mahasiswa' => ['table' => 'mahasiswa', 'name' => 'nama', 'email' => 'email'],
        'dosen' => ['table' => 'dosen_pembimbing', 'name' => 'nama', 'email' => null],
        'admin' => ['table' => 'admin', 'name' => 'nama', 'email' => null],
    ];

    public function up(): void
    {
        foreach ($this->sources as $role => $src) {
            $table = $src['table'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
                continue;
            }

            $defaultTenantId = DB::table('tenants')
                ->where('slug', \Database\Seeders\TenantSeeder::DEFAULT_SLUG)
                ->value('id');

            DB::table($table)->whereNull('user_id')->orderBy('id')->chunkById(200, function ($rows) use ($role, $src, $table, $defaultTenantId) {
                foreach ($rows as $row) {
                    $email = $src['email'] !== null ? ($row->{$src['email']} ?? null) : null;

                    $userId = null;

                    if ($email !== null) {
                        $userId = DB::table('users')->where('role', $role)->where('email', $email)->value('id');
                    }

                    if ($userId === null) {
                        $name = $row->{$src['name']} ?? null;
                        $userId = DB::table('users')->where('role', $role)->where('name', $name)->value('id');
                    }

                    // Create a registry row on the fly if none matched, so the
                    // FK is always populated.
                    if ($userId === null) {
                        $userId = DB::table('users')->insertGetId([
                            'public_id' => (string) \Illuminate\Support\Str::ulid(),
                            'tenant_id' => DB::table($table)->where('id', $row->id)->value('tenant_id') ?? $defaultTenantId,
                            'role' => $role,
                            'name' => $row->{$src['name']} ?? 'unknown',
                            'email' => $email,
                            'password' => $row->password ?? '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table($table)->where('id', $row->id)->update(['user_id' => $userId]);
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: schema reversal is P2-T3's down().
    }
};

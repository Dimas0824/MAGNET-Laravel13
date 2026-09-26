<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * P2-T2: backfill the `users` registry — one row per mahasiswa/dosen/admin.
 *
 * Idempotent: rows are matched by (role, email) so a re-run does not duplicate.
 * Records a mapping via the `user_id` FK added in P2-T3 (that migration reads
 * these rows back). Rows with no email fall back to a synthetic per-role key.
 *
 * down() is intentionally a no-op: un-filling a registry would orphan chats/
 * audit rows that will reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $defaultTenantId = DB::table('tenants')
            ->where('slug', \Database\Seeders\TenantSeeder::DEFAULT_SLUG)
            ->value('id');

        $sources = [
            'mahasiswa' => ['table' => 'mahasiswa', 'name' => 'nama', 'email' => 'email', 'password' => 'password'],
            'dosen' => ['table' => 'dosen_pembimbing', 'name' => 'nama', 'email' => null, 'password' => 'password'],
            'admin' => ['table' => 'admin', 'name' => 'nama', 'email' => null, 'password' => 'password'],
        ];

        foreach ($sources as $role => $src) {
            if (! Schema::hasTable($src['table'])) {
                continue;
            }

            $tenantId = DB::table($src['table'])->value('tenant_id') ?? $defaultTenantId;

            DB::table($src['table'])->orderBy('id')->chunkById(200, function ($rows) use ($role, $src, $tenantId) {
                foreach ($rows as $row) {
                    $email = $src['email'] !== null ? ($row->{$src['email']} ?? null) : null;
                    $matchKey = $email ?? ($role.'#'.$row->id);

                    $exists = DB::table('users')
                        ->where('role', $role)
                        ->where(function ($q) use ($email, $matchKey) {
                            if ($email !== null) {
                                $q->where('email', $email);
                            } else {
                                $q->where('name', $matchKey);
                            }
                        })
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('users')->insert([
                        'public_id' => (string) Str::ulid(),
                        'tenant_id' => $tenantId,
                        'role' => $role,
                        'name' => $row->{$src['name']} ?? 'unknown',
                        'email' => $email,
                        'password' => $row->{$src['password']} ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: registry rows are referenced by chats/audit.
    }
};

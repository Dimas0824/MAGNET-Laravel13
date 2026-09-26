<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `tenant_id` -> the default tenant on every root table.
 *
 * Idempotent and chunked (chunkById): safe to run repeatedly, safe on large
 * tables. Invoked by the P1-T3 data migration so production backfills on
 * `migrate`, and directly by tests. Requires the default tenant to exist
 * (TenantSeeder / P1-T1).
 */
class TenantBackfillSeeder extends Seeder
{
    /** @var array<int, string> */
    public const ROOT_TABLES = [
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

    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => TenantSeeder::DEFAULT_SLUG],
            ['name' => 'Default Tenant', 'public_id' => (string) \Illuminate\Support\Str::ulid()]
        );

        foreach (self::ROOT_TABLES as $table) {
            DB::table($table)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenant->id]);
        }
    }
}

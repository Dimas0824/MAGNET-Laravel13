<?php

use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: every root row gets the default tenant. Idempotent and chunked.
 * After this, tenant_id is safe to make NOT NULL (P1-T7).
 */
const TENANT_BACKFILL_ROOT_TABLES = [
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

beforeEach(function () {
    seedMasterData();
});

it('backfills tenant_id to the default tenant on every root table', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    lowonganMagang();

    (new TenantSeeder)->run();
    $default = Tenant::where('slug', 'default')->sole();

    // Backfill runs as a migration in the real app; here we invoke the same logic
    // directly to assert it is idempotent and complete.
    (new \Database\Seeders\TenantBackfillSeeder)->run();
    (new \Database\Seeders\TenantBackfillSeeder)->run();

    foreach (TENANT_BACKFILL_ROOT_TABLES as $table) {
        $nulls = DB::table($table)->whereNull('tenant_id')->count();
        expect($nulls)->toBe(0, "{$table} still has NULL tenant_id rows");
    }

    expect(DB::table('mahasiswa')->where('id', $mahasiswa->id)->value('tenant_id'))->toBe($default->id);
});

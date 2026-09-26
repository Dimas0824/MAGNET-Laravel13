<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed the single default tenant. Idempotent: re-running updates nothing and
 * inserts nothing new (keys on `slug`).
 */
class TenantSeeder extends Seeder
{
    public const DEFAULT_SLUG = 'default';

    public function run(): void
    {
        Tenant::firstOrCreate(
            ['slug' => self::DEFAULT_SLUG],
            [
                'name' => 'Default Tenant',
                'public_id' => (string) Str::ulid(),
            ]
        );
    }
}

<?php

use Database\Seeders\TenantBackfillSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill tenant_id -> default tenant on all root tables (data-only, idempotent).
 *
 * down() is intentionally a no-op: reverting to "NULL tenant_id" would undo a
 * data fill that the NOT NULL contract (P1-T7) depends on. The column drop in
 * the paired P1-T2 down() reverses the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new TenantBackfillSeeder)->run();
    }

    public function down(): void
    {
        // Intentionally empty: data backfill is not reverted.
    }
};

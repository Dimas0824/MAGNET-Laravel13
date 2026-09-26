<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T1: turn `users` into a REGISTRY of identities.
 *
 * Adds a `public_id` (ULID, char(26)) so an identity can be referenced
 * externally without leaking the auto-increment id, plus a (tenant_id, role)
 * index for "list users by role per tenant".
 *
 * DO NOT touch config/auth.php: the 3 guards/providers stay as-is. The registry
 * is a normal model, never an auth provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('users', 'public_id')) {
                $blueprint->char('public_id', 26)->nullable()->after('id')->unique();
            }
        });

        // Registry rows for dosen/admin have no email (they log in with nidn/nip),
        // so the column must be nullable (ADR 03: users.email is [null]).
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->string('email')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->index(['tenant_id', 'role'], 'users_tenant_id_role_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->dropIndex('users_tenant_id_role_index');
        });

        if (Schema::hasColumn('users', 'public_id')) {
            Schema::table('users', function (Blueprint $blueprint) {
                $blueprint->dropUnique(['public_id']);
                $blueprint->dropColumn('public_id');
            });
        }

        // Restore the pre-registry NOT NULL email shape.
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->string('email')->nullable(false)->change();
        });
    }
};

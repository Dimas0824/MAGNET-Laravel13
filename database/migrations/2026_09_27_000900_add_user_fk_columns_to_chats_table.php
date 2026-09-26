<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T5: add `sender_user_id` / `receiver_user_id` FKs to `chats`, pointing at
 * the `users` registry.
 *
 * Nullable here so the backfill (P2-T6) can run before the polymorphic columns
 * are dropped (P2-T8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('chats', 'sender_user_id')) {
                $blueprint->foreignId('sender_user_id')->nullable()->after('kontrak_magang_id')->constrained('users')->nullOnDelete();
                $blueprint->index('sender_user_id');
            }

            if (! Schema::hasColumn('chats', 'receiver_user_id')) {
                $blueprint->foreignId('receiver_user_id')->nullable()->after('sender_user_id')->constrained('users')->nullOnDelete();
                $blueprint->index('receiver_user_id');
            }
        });

        // Expand phase: both writer shapes must be valid until the polymorphic
        // contract (P2-T8) drops the legacy columns.
        Schema::table('chats', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('sender_id')->nullable()->change();
            $blueprint->unsignedBigInteger('receiver_id')->nullable()->change();
            $blueprint->string('sender_type')->nullable()->change();
            $blueprint->string('receiver_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $blueprint) {
            if (Schema::hasColumn('chats', 'sender_user_id')) {
                $blueprint->dropConstrainedForeignId('sender_user_id');
            }
            if (Schema::hasColumn('chats', 'receiver_user_id')) {
                $blueprint->dropConstrainedForeignId('receiver_user_id');
            }
        });
    }
};

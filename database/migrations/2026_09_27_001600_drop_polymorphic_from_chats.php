<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T8 CONTRACT: drop the polymorphic chat participant columns
 * (sender_id/sender_type/receiver_id/receiver_type).
 *
 * Safe only AFTER P2-T6 backfilled `*_user_id` and P2-T7 pointed the model +
 * the broadcast payload at the registry. down() restores the columns so the
 * schema stays reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $blueprint) {
            foreach (['sender_id', 'sender_type', 'receiver_id', 'receiver_type'] as $col) {
                if (Schema::hasColumn('chats', $col)) {
                    $blueprint->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chats')) {
            return;
        }

        Schema::table('chats', function (Blueprint $blueprint) {
            if (! Schema::hasColumn('chats', 'sender_id')) {
                $blueprint->unsignedBigInteger('sender_id')->nullable()->after('kontrak_magang_id');
            }
            if (! Schema::hasColumn('chats', 'sender_type')) {
                $blueprint->string('sender_type')->nullable()->after('sender_id');
            }
            if (! Schema::hasColumn('chats', 'receiver_id')) {
                $blueprint->unsignedBigInteger('receiver_id')->nullable()->after('receiver_user_id');
            }
            if (! Schema::hasColumn('chats', 'receiver_type')) {
                $blueprint->string('receiver_type')->nullable()->after('receiver_id');
            }
        });
    }
};

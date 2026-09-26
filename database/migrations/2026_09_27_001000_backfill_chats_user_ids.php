<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T6: backfill `chats.sender_user_id` / `receiver_user_id` from the
 * polymorphic pair + the kontrak, resolving each participant to its `user_id`:
 *
 *   mahasiswa role -> kontrak_magang.mahasiswa.user_id
 *   dosen role     -> kontrak_magang.dosen_pembimbing.user_id
 *
 * down() is a no-op: the column drop in P2-T5 down() reverses the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chats')
            || ! Schema::hasColumn('chats', 'sender_user_id')
            || ! Schema::hasColumn('chats', 'receiver_user_id')) {
            return;
        }

        // Resolve via the kontrak so we never compare id spaces directly.
        $chats = DB::table('chats')
            ->join('kontrak_magang', 'chats.kontrak_magang_id', '=', 'kontrak_magang.id')
            ->leftJoin('mahasiswa', 'kontrak_magang.mahasiswa_id', '=', 'mahasiswa.id')
            ->leftJoin('dosen_pembimbing', 'kontrak_magang.dosen_id', '=', 'dosen_pembimbing.id')
            ->select(
                'chats.id',
                'chats.sender_type',
                'chats.receiver_type',
                'mahasiswa.user_id as mahasiswa_user_id',
                'dosen_pembimbing.user_id as dosen_user_id',
            )
            ->get();

        foreach ($chats as $chat) {
            $sender = $chat->sender_type === 'mahasiswa' ? $chat->mahasiswa_user_id : $chat->dosen_user_id;
            $receiver = $chat->receiver_type === 'mahasiswa' ? $chat->mahasiswa_user_id : $chat->dosen_user_id;

            DB::table('chats')->where('id', $chat->id)->update([
                'sender_user_id' => $sender,
                'receiver_user_id' => $receiver,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty: schema reversal is P2-T5's down().
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat rows previously relied on raw sender_id/receiver_id, which collide
     * because mahasiswa and dosen live in different tables but share the same
     * id space. That made "is this message mine?" ambiguous whenever
     * mahasiswa.id happened to equal dosen.id (e.g. both = 1), collapsing every
     * bubble to one side. These columns disambiguate the participant role.
     */
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->enum('sender_type', ['mahasiswa', 'dosen'])->default('mahasiswa')->after('sender_id');
            $table->enum('receiver_type', ['mahasiswa', 'dosen'])->default('dosen')->after('receiver_id');
        });

        // Backfill existing rows: the mahasiswa is authoritative from the
        // kontrak, so anything not the mahasiswa must be the dosen.
        DB::table('chats')
            ->join('kontrak_magang', 'chats.kontrak_magang_id', '=', 'kontrak_magang.id')
            ->select('chats.id', 'chats.sender_id', 'chats.receiver_id', 'kontrak_magang.mahasiswa_id')
            ->orderBy('chats.id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('chats')->where('id', $row->id)->update([
                        'sender_type' => (int) $row->sender_id === (int) $row->mahasiswa_id ? 'mahasiswa' : 'dosen',
                        'receiver_type' => (int) $row->receiver_id === (int) $row->mahasiswa_id ? 'mahasiswa' : 'dosen',
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn(['sender_type', 'receiver_type']);
        });
    }
};

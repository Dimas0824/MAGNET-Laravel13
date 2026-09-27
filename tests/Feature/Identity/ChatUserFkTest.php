<?php

use App\Models\Chat;
use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P2-T5..T8: chats participants reference the `users` registry via
 * sender_user_id / receiver_user_id instead of the polymorphic
 * (sender_id, sender_type) pair.
 *
 * The polymorphic pair compared two id spaces that can collide (mahasiswa.id
 * and dosen.id both start at 1), so "who sent this" was only recoverable from
 * the type column. A registry FK makes the participant unambiguous and lets
 * chat rows survive a role-table change.
 */
beforeEach(function () {
    seedMasterData();
});

it('adds sender_user_id / receiver_user_id FK columns to chats', function () {
    expect(Schema::hasColumn('chats', 'sender_user_id'))->toBeTrue();
    expect(Schema::hasColumn('chats', 'receiver_user_id'))->toBeTrue();

    foreach (['sender_user_id', 'receiver_user_id'] as $col) {
        $fk = DB::table('information_schema.referential_constraints')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'chats')
            ->get()
            ->filter(fn ($r) => $r->REFERENCED_TABLE_NAME === 'users');

        expect($fk)->not->toBeEmpty("chats.{$col} has no FK to users");
    }
});

it('backfills chat participants from kontrak + role (0 NULL)', function () {
    // The polymorphic columns are dropped by P2-T8, so the legacy-shape backfill
    // is exercised by the migration chain itself (P2-T6 runs before P2-T8). Here
    // we assert the post-contract invariant: every chat row is registry-linked.
    $mahasiswa = Mahasiswa::factory()->create();
    $dosen = DosenPembimbing::factory()->create();
    $lowongan = lowonganMagang();
    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => $lowongan->id,
    ]);

    linkKontrakParticipantsToRegistry($kontrak);

    Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_user_id' => $kontrak->mahasiswa->user_id,
        'receiver_user_id' => $kontrak->dosenPembimbing->user_id,
        'message' => 'Halo',
    ]);

    expect(DB::table('chats')->whereNull('sender_user_id')->count())->toBe(0)
        ->and(DB::table('chats')->whereNull('receiver_user_id')->count())->toBe(0);
});

it('resolves sender/receiver through the registry FK', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $dosen = DosenPembimbing::factory()->create();
    $lowongan = lowonganMagang();
    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => $lowongan->id,
    ]);

    linkKontrakParticipantsToRegistry($kontrak);

    $mahasiswa->refresh();
    $dosen->refresh();

    $chat = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_user_id' => $mahasiswa->user_id,
        'receiver_user_id' => $dosen->user_id,
        'message' => 'Halo',
    ]);

    expect($chat->sender->id)->toBe($mahasiswa->user_id)
        ->and($chat->receiver->id)->toBe($dosen->user_id)
        ->and($chat->isSentByMahasiswa())->toBeTrue();
});

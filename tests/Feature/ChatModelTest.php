<?php

use App\Models\Chat;
use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;

beforeEach(function () {
    seedMasterData();
});

function chatFixture(): array
{
    // Use explicit, distinct ids so sender/receiver role detection is
    // unambiguous (mahasiswa_id and dosen_id must not collide).
    $kontrak = KontrakMagang::factory()->create();

    $mahasiswaId = $kontrak->mahasiswa_id;

    // Create a real dosen row and point the kontrak at it, ensuring the id
    // differs from the mahasiswa id.
    $dosen = DosenPembimbing::factory()->create();
    if ($dosen->id === $mahasiswaId) {
        $dosen = DosenPembimbing::factory()->create();
    }
    $kontrak->update(['dosen_id' => $dosen->id]);
    $kontrak->refresh();
    $dosenId = $kontrak->dosen_id;

    $dariMahasiswa = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $kontrak->mahasiswa_id,
        'receiver_id' => $dosenId,
        'message' => 'Halo dosen',
    ]);

    $dariDosen = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $dosenId,
        'receiver_id' => $kontrak->mahasiswa_id,
        'message' => 'Halo mahasiswa',
    ]);

    return [$kontrak, $dariMahasiswa, $dariDosen];
}

it('resolves the sender and receiver for a mahasiswa message', function () {
    [$kontrak, $chat] = chatFixture();

    expect($chat->sender->id)->toBe($kontrak->mahasiswa_id)
        ->and($chat->receiver->id)->toBe($kontrak->dosen_id)
        ->and($chat->isSentByMahasiswa())->toBeTrue()
        ->and($chat->isSentByDosen())->toBeFalse();
});

it('resolves the sender and receiver for a dosen message', function () {
    [$kontrak, , $chat] = chatFixture();

    expect($chat->sender->id)->toBe($kontrak->dosen_id)
        ->and($chat->receiver->id)->toBe($kontrak->mahasiswa_id)
        ->and($chat->isSentByDosen())->toBeTrue()
        ->and($chat->isSentByMahasiswa())->toBeFalse();
});

it('scopes messages between two users', function () {
    [$kontrak] = chatFixture();

    $between = Chat::betweenUsers($kontrak->mahasiswa_id, $kontrak->dosen_id, $kontrak->id)->count();

    expect($between)->toBe(2);
});

it('scopes messages by kontrak', function () {
    [$kontrak] = chatFixture();

    expect(Chat::byKontrak($kontrak->id)->count())->toBe(2);
});

it('returns null sender/receiver when the kontrak is missing', function () {
    $chat = new Chat([
        'kontrak_magang_id' => 999999,
        'sender_id' => 1,
        'receiver_id' => 2,
        'message' => 'orphan',
    ]);

    expect($chat->sender)->toBeNull()
        ->and($chat->receiver)->toBeNull()
        ->and($chat->isSentByMahasiswa())->toBeFalse()
        ->and($chat->isSentByDosen())->toBeFalse();
});

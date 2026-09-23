<?php

use App\Models\Chat;
use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;

beforeEach(function () {
    seedMasterData();
});

function chatFixture(): array
{
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
        'sender_type' => Chat::SENDER_MAHASISWA,
        'receiver_id' => $dosenId,
        'receiver_type' => Chat::SENDER_DOSEN,
        'message' => 'Halo dosen',
    ]);

    $dariDosen = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $dosenId,
        'sender_type' => Chat::SENDER_DOSEN,
        'receiver_id' => $kontrak->mahasiswa_id,
        'receiver_type' => Chat::SENDER_MAHASISWA,
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
        'sender_type' => Chat::SENDER_MAHASISWA,
        'receiver_id' => 2,
        'receiver_type' => Chat::SENDER_DOSEN,
        'message' => 'orphan',
    ]);

    expect($chat->sender)->toBeNull()
        ->and($chat->receiver)->toBeNull()
        ->and($chat->isSentByMahasiswa())->toBeTrue()
        ->and($chat->isSentByDosen())->toBeFalse();
});

it('resolves roles correctly when mahasiswa and dosen share the same id (regression)', function () {
    // Force the collision that broke the old raw-id comparison: the mahasiswa
    // and the dosen both have primary key 1.
    $mahasiswa = Mahasiswa::forceCreate([
        'id' => 1,
        'nama' => 'Collision Mhs',
        'nim' => '9990001',
        'email' => 'collision@magnet.test',
        'password' => bcrypt('password'),
        'angkatan' => 22,
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '2003-01-01',
        'jurusan' => 'Teknologi Informasi',
        'program_studi' => 'D4 Teknik Informatika',
        'alamat' => 'Jl. Test No. 1',
        'status_magang' => 'sedang magang',
    ]);

    $dosen = DosenPembimbing::forceCreate([
        'id' => 1,
        'nama' => 'Collision Dosen',
        'nidn' => '9990002',
        'password' => bcrypt('password'),
        'jenis_kelamin' => 'P',
    ]);

    expect($mahasiswa->id)->toBe($dosen->id);

    $kontrak = KontrakMagang::forceCreate([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => lowonganMagang()->id,
        'waktu_awal' => now(),
        'waktu_akhir' => now()->addMonths(3),
        'status' => 'disetujui',
    ]);

    $fromMhs = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $mahasiswa->id,
        'sender_type' => Chat::SENDER_MAHASISWA,
        'receiver_id' => $dosen->id,
        'receiver_type' => Chat::SENDER_DOSEN,
        'message' => 'dari mahasiswa',
    ]);

    $fromDosen = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $dosen->id,
        'sender_type' => Chat::SENDER_DOSEN,
        'receiver_id' => $mahasiswa->id,
        'receiver_type' => Chat::SENDER_MAHASISWA,
        'message' => 'dari dosen',
    ]);

    // Same raw ids, opposite roles: the message must still resolve correctly.
    expect($fromMhs->isSentByMahasiswa())->toBeTrue()
        ->and($fromMhs->isSentByDosen())->toBeFalse()
        ->and($fromDosen->isSentByDosen())->toBeTrue()
        ->and($fromDosen->isSentByMahasiswa())->toBeFalse()
        ->and($fromMhs->isMineFor(Chat::SENDER_MAHASISWA))->toBeTrue()
        ->and($fromMhs->isMineFor(Chat::SENDER_DOSEN))->toBeFalse();
});
